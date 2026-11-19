<?php

namespace App\Tests\Cliente;

use App\Asesoria\Domain\Firm;
use App\Asesoria\Domain\FirmRepository;
use App\Cliente\Application\LinkExistingClient;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\ClientRepository;
use App\Cliente\Domain\FirmLinkInvitation;
use App\Cliente\Domain\FirmLinkInvitations;
use App\Cliente\Domain\FirmLinkNotifier;
use App\Documento\Application\DocumentForm;
use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentRepository;
use App\Documento\Domain\DocumentStatus;
use App\Finanzas\Domain\Quarter;
use App\Finanzas\Domain\QuarterPeriod;
use App\Finanzas\Domain\QuarterRepository;
use App\Finanzas\Domain\QuarterSummary;
use App\Identidad\Domain\AccountTransaction;
use App\Identidad\Domain\InvitationUnavailable;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\TokenGenerator;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\ClientTransaction;
use App\Shared\Domain\Clock;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\NullSecurityAudit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LinkExistingClientTest extends TestCase
{
    private const OWNER = '00000000-0000-4000-8000-000000000001';
    private const ACTOR = '00000000-0000-4000-8000-000000000002';
    private const FIRM = '00000000-0000-4000-8000-000000000003';
    private User $owner;
    private User $actor;
    private Client $client;
    /** @var array<string, FirmLinkInvitation> */
    private array $invitations = [];
    /** @var list<string> */
    private array $tokens = [];
    /** @var list<Document> */
    private array $savedDocuments = [];
    private \DateTimeImmutable $now;
    private ?\Closure $intervening = null;

    protected function setUp(): void
    {
        $this->owner = new User(self::OWNER, 'synthetic@example.test', 'unchanged-hash', Role::Client, null, true);
        $this->actor = new User(self::ACTOR, 'advisor@example.test', 'synthetic-hash', Role::Advisor, self::FIRM, true);
        $this->client = new Client(self::OWNER, self::OWNER, ClientKind::Company, 'Empresa ficticia', null);
        $this->now = new \DateTimeImmutable('2026-11-18 12:00:00 UTC');
    }

    /** @param list<Document> $documents */
    private function service(array $documents = [], ?Quarter $quarter = null): LinkExistingClient
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('find')->willReturnCallback(fn (string $id): ?User => match ($id) {
            self::OWNER => $this->owner, self::ACTOR => $this->actor, default => null,
        });
        $users->method('findByEmail')->willReturnCallback(fn (string $email): ?User => mb_strtolower(trim($email)) === $this->owner->email() ? $this->owner : null);
        $users->method('save')->willReturnCallback(function (User $user): void { $this->owner = $user; });
        $clients = $this->createStub(ClientRepository::class);
        $clients->method('findByUserId')->willReturnCallback(fn (): Client => $this->client);
        $clients->method('save')->willReturnCallback(function (Client $client): void { $this->client = $client; });
        $firms = $this->createStub(FirmRepository::class);
        $firms->method('find')->willReturn(new Firm(self::FIRM, 'Asesoría ficticia'));
        $invitations = $this->createStub(FirmLinkInvitations::class);
        $invitations->method('findByHash')->willReturnCallback(function (string $hash): ?FirmLinkInvitation {
            foreach ($this->invitations as $invitation) {
                if ($hash === $invitation->tokenHash) {
                    return $invitation;
                }
            }

            return null;
        });
        $invitations->method('forPair')->willReturnCallback(fn (): ?FirmLinkInvitation => $this->invitations['pair'] ?? null);
        $invitations->method('save')->willReturnCallback(function (FirmLinkInvitation $invitation): void { $this->invitations['pair'] = $invitation; });
        $notifier = $this->createStub(FirmLinkNotifier::class);
        $notifier->method('send')->willReturnCallback(function (string $email, string $name, string $token): void { $this->tokens[] = $token; });
        $tokens = $this->createStub(TokenGenerator::class);
        $tokens->method('next')->willReturnCallback(fn (): string => hash('sha256', 'synthetic-'.count($this->tokens)));
        $ids = $this->createStub(IdGenerator::class);
        $ids->method('next')->willReturn(self::OWNER);
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturnCallback(fn (): \DateTimeImmutable => $this->now);
        $transaction = $this->createStub(ClientTransaction::class);
        $transaction->method('run')->willReturnCallback(function (string $owner, callable $operation): mixed {
            if (null !== $this->intervening) {
                ($this->intervening)();
                $this->intervening = null;
            }

            return $operation();
        });
        $accounts = $this->createStub(AccountTransaction::class);
        $accounts->method('forUser')->willReturnCallback(static function (string $owner, callable $operation): void { $operation(); });
        $repository = $this->createStub(DocumentRepository::class);
        $repository->method('findByOwner')->willReturn($documents);
        $repository->method('save')->willReturnCallback(function (Document $document): void { $this->savedDocuments[] = $document; });
        $quarters = $this->createStub(QuarterRepository::class);
        $quarters->method('find')->willReturnCallback(static fn (string $owner, ?string $firm, QuarterPeriod $period): ?Quarter => $quarter?->period->key() === $period->key() ? $quarter : null);

        return new LinkExistingClient($users, $clients, $firms, $invitations, $notifier, $tokens, $ids, $clock, $transaction, $accounts, $repository, $quarters, new NullSecurityAudit());
    }

    public function testAdvisorCanInviteAndResendWithoutGrantingAccess(): void
    {
        $service = $this->service();
        $service->send(self::ACTOR, ' SYNTHETIC@example.test ');
        $old = $this->tokens[0];
        self::assertNull($this->owner->firmId());
        self::assertNull($this->client->firmId());
        self::assertNotSame($old, $this->invitations['pair']->tokenHash);
        $service->send(self::ACTOR, $this->owner->email());
        self::assertCount(1, $this->invitations);
        self::assertEquals($this->now->modify('+24 hours'), $this->invitations['pair']->expiresAt);
        $this->expectException(InvitationUnavailable::class);
        $service->accept(self::OWNER, $old);
    }

    public function testAcceptancePreservesAccountAndPersonalClosedRecordsButMovesOpenRecords(): void
    {
        $closed = $this->document('2026-03-01');
        $open = $this->document('2026-11-01');
        $quarter = Quarter::firstClose(self::OWNER, null, new QuarterPeriod(2026, 1), new QuarterSummary([]), self::OWNER, $this->now);
        $service = $this->service([$closed, $open], $quarter);
        $this->actor = new User(self::ACTOR, 'admin@example.test', 'hash', Role::FirmAdmin, self::FIRM, true);
        $service->send(self::ACTOR, $this->owner->email());
        $before = $this->owner;
        $service->accept(self::OWNER, $this->tokens[0]);
        self::assertSame(self::FIRM, $this->owner->firmId());
        self::assertSame(self::FIRM, $this->client->firmId());
        self::assertSame(Role::Client, $this->owner->role());
        self::assertSame($before->passwordHash(), $this->owner->passwordHash());
        self::assertSame($before->authVersion() + 1, $this->owner->authVersion());
        self::assertCount(1, $this->savedDocuments);
        $moved = $this->savedDocuments[0];
        self::assertSame(self::FIRM, $moved->firmId());
        self::assertSame(DocumentStatus::Submitted, $moved->status());
        self::assertSame($open->reviewHistory(), $moved->reviewHistory());
        self::assertEquals($open->details(), $moved->details());
        self::assertSame($open->storedPath(), $moved->storedPath());
        self::assertNull($closed->firmId());
        self::assertTrue($quarter->closed);
        self::assertNotNull($this->invitations['pair']->acceptedAt);
    }

    /** @return iterable<string, array{Role}> */
    public static function deniedSenders(): iterable
    {
        yield 'client' => [Role::Client];
        yield 'superadmin' => [Role::Superadmin];
    }

    #[DataProvider('deniedSenders')]
    public function testOtherRolesCannotInvite(Role $role): void
    {
        $this->actor = new User(self::ACTOR, 'synthetic-other@example.test', 'hash', $role, null, true);
        $this->expectException(InvitationUnavailable::class);
        $this->service()->send(self::ACTOR, $this->owner->email());
    }

    public function testWrongAccountCannotAccept(): void
    {
        $service = $this->service();
        $service->send(self::ACTOR, $this->owner->email());
        $this->expectException(InvitationUnavailable::class);
        $service->accept(self::ACTOR, $this->tokens[0]);
    }

    public function testExpiryIncludesExactBoundary(): void
    {
        $service = $this->service();
        $service->send(self::ACTOR, $this->owner->email());
        $this->now = $this->now->modify('+24 hours');
        $this->expectException(InvitationUnavailable::class);
        $service->accept(self::OWNER, $this->tokens[0]);
    }

    public function testConcurrentLinkIsRecheckedAfterClientLock(): void
    {
        $service = $this->service();
        $service->send(self::ACTOR, $this->owner->email());
        $this->intervening = function (): void { $this->client = $this->client->joinFirm('00000000-0000-4000-8000-000000000099'); };
        $this->expectException(InvitationUnavailable::class);
        $service->accept(self::OWNER, $this->tokens[0]);
    }

    private function document(string $date): Document
    {
        return Document::receive(self::OWNER, self::OWNER, null, 'synthetic.pdf', 'synthetic/original.pdf', 'application/pdf', $this->now)->confirm(DocumentForm::read('gasto', $date, 'Tercero ficticio', '00000000T', '100', '21'), $this->now);
    }
}
