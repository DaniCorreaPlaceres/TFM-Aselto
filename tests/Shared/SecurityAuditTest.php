<?php

namespace App\Tests\Shared;

use App\Shared\Application\SecurityAlertSummary;
use App\Shared\Domain\Clock;
use App\Shared\Domain\NullSecurityAudit;
use App\Shared\Domain\SecurityEntry;
use App\Shared\Domain\SecurityEvent;
use App\Shared\Infrastructure\Security\MonologSecurityAudit;
use App\Shared\Infrastructure\Security\PrivateSecurityJournal;
use App\Shared\Infrastructure\Security\SecurityAlertDelivery;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Uid\Uuid;

final class SecurityAuditTest extends TestCase
{
    private string $directory;
    private \DateTimeImmutable $now;
    private Clock $clock;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/aselto-security-test-'.bin2hex(random_bytes(8));
        $this->now = new \DateTimeImmutable('2026-11-15T12:00:00Z');
        $this->clock = $this->createStub(Clock::class);
        $this->clock->method('now')->willReturnCallback(fn () => $this->now);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $path) {
            unlink($path);
        }
        foreach (glob($this->directory.'/.*') ?: [] as $path) {
            if (!in_array(basename($path), ['.', '..'], true)) {
                unlink($path);
            }
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    private function entry(SecurityEvent $event = SecurityEvent::LoginFailed, ?\DateTimeImmutable $at = null): SecurityEntry
    {
        return new SecurityEntry(Uuid::v4()->toRfc4122(), $at ?? $this->now, $event, str_repeat('a', 32));
    }

    private function journal(): PrivateSecurityJournal
    {
        return new PrivateSecurityJournal($this->directory, $this->clock);
    }

    public function testClosedSchemaRejectsPayloadsAndRestoresCanonicalEntry(): void
    {
        $entry = $this->entry();
        self::assertSame($entry->data(), SecurityEntry::restore($entry->data())->data());
        $this->expectException(\InvalidArgumentException::class);
        SecurityEntry::restore($entry->data() + ['password' => 'SECRET']);
    }

    public function testJournalProtectsFilesResumesCursorAndAllowsAppendDuringScan(): void
    {
        $journal = $this->journal();
        $first = $this->entry();
        $journal->append($first);
        $scan = $journal->scan();
        $scan->rewind();
        $cursor = $scan->key();
        $journal->append($this->entry());
        $scan->next();
        self::assertFalse($scan->valid(), 'A scan snapshots the file size.');
        self::assertCount(1, iterator_to_array($journal->scan($cursor)));
        self::assertTrue($journal->healthy());
        self::assertSame(0700, fileperms($this->directory) & 0777);
        self::assertSame(0600, fileperms($this->directory.'/security-2026-11-15.jsonl') & 0777);
    }

    public function testRetentionUsesUtcAndLeavesOtherFilesUntouched(): void
    {
        $journal = $this->journal();
        $journal->append($this->entry(at: $this->now->modify('-30 days')));
        $journal->append($this->entry(at: $this->now->modify('-29 days')));
        file_put_contents($this->directory.'/unrelated.txt', 'preserve');
        self::assertSame(1, $journal->purge());
        self::assertCount(1, iterator_to_array($journal->scan()));
        self::assertSame('preserve', file_get_contents($this->directory.'/unrelated.txt'));
    }

    public function testSymlinkIsRejectedWithoutDeletingTarget(): void
    {
        $journal = $this->journal();
        $journal->directory();
        $target = $this->directory.'/unrelated.txt';
        file_put_contents($target, 'preserve');
        symlink($target, $this->directory.'/security-2026-11-15.jsonl');
        self::assertFalse($journal->healthy());
        try {
            $journal->append($this->entry());
            self::fail('Symlink accepted.');
        } catch (\RuntimeException) {
            self::assertSame('preserve', file_get_contents($target));
        }
    }

    public function testCapacityAndCorruptionFailClosedForOperators(): void
    {
        $journal = $this->journal();
        $journal->append($this->entry());
        $path = $this->directory.'/security-2026-11-15.jsonl';
        $file = fopen($path, 'ab');
        ftruncate($file, PrivateSecurityJournal::MAX_FILE_BYTES);
        fclose($file);
        self::assertFalse($journal->healthy());
        try {
            $journal->append($this->entry());
            self::fail('Capacity exceeded.');
        } catch (\RuntimeException) {
            self::assertSame(PrivateSecurityJournal::MAX_FILE_BYTES, filesize($path));
        }
        file_put_contents($path, "{broken}\n");
        $this->expectException(\JsonException::class);
        iterator_to_array($journal->scan());
    }

    public function testContextHashesPrivateDataAndIgnoresIncomingCorrelation(): void
    {
        $handler = new TestHandler();
        $requests = new RequestStack();
        $request = Request::create('/private/SECRET?token=SECRET', server: ['REMOTE_ADDR' => '192.0.2.1', 'HTTP_X_REQUEST_ID' => 'attacker']);
        $request->attributes->set('_route', 'test_route');
        $requests->push($request);
        $audit = new MonologSecurityAudit(new Logger('security', [$handler]), $requests, $this->clock, 'synthetic-secret');
        $audit->record(SecurityEvent::LoginFailed, subject: " SYNTHETIC@example.test\r\n");
        $entry = $handler->getRecords()[0]->context['entry'];
        self::assertInstanceOf(SecurityEntry::class, $entry);
        self::assertSame(hash_hmac('sha256', 'audit-subject|synthetic@example.test', 'synthetic-secret'), $entry->subjectHash);
        self::assertSame(hash_hmac('sha256', 'audit-ip|192.0.2.1', 'synthetic-secret'), $entry->ipHash);
        self::assertSame($request->attributes->get(MonologSecurityAudit::CORRELATION), $entry->correlation);
        $json = json_encode($entry->data(), JSON_THROW_ON_ERROR);
        foreach (['SECRET', 'example.test', '192.0.2.1', 'attacker', '\\r', '\\n'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $json);
        }
    }

    public function testAlertDeliveryIsHourlyAndContainsOnlyCounts(): void
    {
        $journal = $this->journal();
        for ($i = 0; $i < 10; ++$i) {
            $journal->append($this->entry());
        }
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::exactly(2))->method('send')->willReturnCallback(function ($email) {
            self::assertStringContainsString('authentication_failures', $email->getTextBody());
            self::assertStringNotContainsString('correlation', $email->getTextBody());
            self::assertSame('synthetic@example.test', $email->getTo()[0]->getAddress());

            return null;
        });
        $delivery = $this->delivery($journal, $transport);
        self::assertSame('sent', $delivery->send());
        for ($i = 0; $i < 10; ++$i) {
            $journal->append($this->entry());
        }
        self::assertSame('waiting', $delivery->send());
        $this->now = $this->now->modify('+1 hour');
        self::assertSame('sent', $delivery->send());
        $this->now = $this->now->modify('+1 hour');
        self::assertSame('empty', $delivery->send());
    }

    public function testFailureKeepsCursorAndDisabledModeNeverSends(): void
    {
        $journal = $this->journal();
        for ($i = 0; $i < 10; ++$i) {
            $journal->append($this->entry());
        }
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('send')->willThrowException(new \RuntimeException('SECRET SMTP detail'));
        self::assertSame('disabled', $this->delivery($journal, $transport, false)->send());
        $delivery = $this->delivery($journal, $transport);
        try {
            $delivery->send();
            self::fail('Failed delivery accepted.');
        } catch (\RuntimeException $failure) {
            self::assertSame('Security summary delivery failed.', $failure->getMessage());
        }
        $state = json_decode(file_get_contents($this->directory.'/alerts-state.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertNull($state['cursor']);
        self::assertSame('waiting', $delivery->send());
        $this->now = $this->now->modify('+1 hour');
        $retry = $this->createMock(TransportInterface::class);
        $retry->expects(self::once())->method('send');
        self::assertSame('sent', $this->delivery($journal, $retry)->send());
    }

    public function testConcurrentDeliveryCannotSend(): void
    {
        $journal = $this->journal();
        $journal->directory();
        $locks = new LockFactory(new FlockStore($this->directory));
        $held = $locks->createLock('aselto-security-alerts');
        self::assertTrue($held->acquire());
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::never())->method('send');
        self::assertSame('busy', $this->delivery($journal, $transport)->send());
        $held->release();
    }

    public function testThresholdsDoNotCombineDifferentUtcWindows(): void
    {
        $entries = [];
        for ($i = 0; $i < 9; ++$i) {
            $entries[(string) $i] = $this->entry();
        }
        $entries['next'] = $this->entry(at: $this->now->modify('+15 minutes'));
        self::assertSame([], (new SecurityAlertSummary())->summarize($entries)['alerts']);
    }

    public function testAuditFailureNeverPropagatesOrPrintsSensitiveException(): void
    {
        $this->journal()->directory();
        $path = $this->directory.'/diagnostic.txt';
        $previous = ini_set('error_log', $path);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects(self::once())->method('info')->willThrowException(new \RuntimeException('SYNTHETIC_SECRET'));
        try {
            (new MonologSecurityAudit($logger, new RequestStack(), $this->clock, 'synthetic-secret'))->record(SecurityEvent::PasswordChanged);
            self::assertStringContainsString('private security audit unavailable', file_get_contents($path));
            self::assertStringNotContainsString('SYNTHETIC_SECRET', file_get_contents($path));
        } finally {
            ini_set('error_log', $previous);
        }
    }

    private function delivery(PrivateSecurityJournal $journal, TransportInterface $transport, bool $enabled = true): SecurityAlertDelivery
    {
        return new SecurityAlertDelivery($journal, new SecurityAlertSummary(), $this->clock, new LockFactory(new FlockStore($journal->directory())), $transport, new NullSecurityAudit(), $enabled, 'synthetic@example.test', 'synthetic-sender@example.test');
    }
}
