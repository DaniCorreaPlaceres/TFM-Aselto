<?php

namespace App\Tests\Finanzas;

use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\ClientRepository;
use App\Finanzas\Application\ChangeQuarter;
use App\Finanzas\Application\PreventClosedQuarterChanges;
use App\Finanzas\Application\QuarterAccess;
use App\Finanzas\Application\ShowQuarter;
use App\Finanzas\Domain\FinancialRecord;
use App\Finanzas\Domain\FinancialRecords;
use App\Finanzas\Domain\InvalidQuarter;
use App\Finanzas\Domain\Quarter;
use App\Finanzas\Domain\QuarterChanged;
use App\Finanzas\Domain\QuarterNotFound;
use App\Finanzas\Domain\QuarterPeriod;
use App\Finanzas\Domain\QuarterRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\ClientTransaction;
use App\Shared\Domain\Clock;
use App\Shared\Domain\ClosedPeriod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuarterWorkflowTest extends TestCase
{
    private const OWNER = '00000000-0000-4000-8000-000000000001';
    private const CLIENT = '00000000-0000-4000-8000-000000000002';
    private const FIRM = '00000000-0000-4000-8000-000000000003';
    private const STAFF = '00000000-0000-4000-8000-000000000004';
    private const OTHER = '00000000-0000-4000-8000-000000000005';
    private const ROOT = '00000000-0000-4000-8000-000000000006';
    private const ADVISOR = '00000000-0000-4000-8000-000000000007';
    private ?string $firm = null;
    /** @var list<FinancialRecord> */
    private array $records = [];
    /** @var array<string, Quarter> */
    private array $saved = [];
    private ShowQuarter $show;
    private ChangeQuarter $change;
    private PreventClosedQuarterChanges $guard;

    protected function setUp(): void
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('find')->willReturnCallback(fn (string $id): ?User => match ($id) {
            self::OWNER => new User($id, 'owner@example.test', 'hash', Role::Client, $this->firm, true),
            self::STAFF => new User($id, 'admin@example.test', 'hash', Role::FirmAdmin, self::FIRM, true),
            self::ADVISOR => new User($id, 'advisor@example.test', 'hash', Role::Advisor, self::FIRM, true),
            self::OTHER => new User($id, 'other@example.test', 'hash', Role::Advisor, self::OTHER, true),
            self::ROOT => new User($id, 'root@example.test', 'hash', Role::Superadmin, null, true),
            default => null,
        });
        $clients = $this->createStub(ClientRepository::class);
        $client = fn (): Client => new Client(self::CLIENT, self::OWNER, ClientKind::SelfEmployed, 'Cliente de ejemplo', $this->firm);
        $clients->method('find')->willReturnCallback(static fn (string $id): ?Client => self::CLIENT === $id ? $client() : null);
        $clients->method('findByUserId')->willReturnCallback(static fn (string $id): ?Client => self::OWNER === $id ? $client() : null);
        $repository = $this->createStub(QuarterRepository::class);
        $key = static fn (string $owner, ?string $firm, QuarterPeriod $period): string => $owner.($firm ?? 'personal').$period->key();
        $repository->method('find')->willReturnCallback(fn (string $owner, ?string $firm, QuarterPeriod $period): ?Quarter => $this->saved[$key($owner, $firm, $period)] ?? null);
        $repository->method('forOwner')->willReturnCallback(fn (string $owner): array => array_values(array_filter($this->saved, static fn (Quarter $q): bool => $owner === $q->ownerId)));
        $repository->method('save')->willReturnCallback(function (Quarter $q) use ($key): void {
            $id = $key($q->ownerId, $q->firmId, $q->period);
            $this->saved[$id] = new Quarter($q->ownerId, $q->firmId, $q->period, $q->closed, $q->snapshot, $q->history, isset($this->saved[$id]) ? $this->saved[$id]->version + 1 : 1);
        });
        $source = $this->createStub(FinancialRecords::class);
        $source->method('forClient')->willReturnCallback(fn (): array => $this->records);
        $transaction = $this->createStub(ClientTransaction::class);
        $transaction->method('run')->willReturnCallback(static fn (string $owner, callable $operation): mixed => $operation());
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-11-09 12:00 UTC'));
        $access = new QuarterAccess($users, $clients);
        $this->show = new ShowQuarter($access, $repository, $source, $clock);
        $this->change = new ChangeQuarter($access, $repository, $source, $transaction, $clock);
        $this->guard = new PreventClosedQuarterChanges($repository);
    }

    public function testCloseReopenCorrectionAndReclosePreserveHistory(): void
    {
        $period = new QuarterPeriod(2026, 4);
        $this->records = [self::record(true)];
        $view = $this->show->execute(self::OWNER, null, $period);
        $this->change->execute(self::OWNER, null, $period, null, true, 0, $view->calculation->fingerprint);
        $this->records = [self::record(false)];
        $closed = $this->show->execute(self::OWNER, null, $period);
        self::assertSame(10000, $closed->record->snapshot->incomeCents());
        self::assertSame(0, $closed->calculation->summary->incomeCents());
        self::assertCount(1, $closed->calculation->pending);
        $this->change->execute(self::OWNER, null, $period, null, false, 1);
        $this->records = [];
        $view = $this->show->execute(self::OWNER, null, $period);
        $this->change->execute(self::OWNER, null, $period, null, true, 2, $view->calculation->fingerprint);
        $latest = $this->show->execute(self::OWNER, null, $period)->record;
        self::assertCount(3, $latest->history);
        self::assertSame(10000, $latest->history[0]->snapshot->incomeCents());
        self::assertSame(0, $latest->snapshot->incomeCents());
    }

    public function testChangedFinancialDataRequiresRefreshingTheScreen(): void
    {
        $period = new QuarterPeriod(2026, 4);
        $view = $this->show->execute(self::OWNER, null, $period);
        $this->records = [self::record(true)];
        $this->expectException(QuarterChanged::class);
        $this->change->execute(self::OWNER, null, $period, null, true, 0, $view->calculation->fingerprint);
    }

    public function testPendingBlocksClose(): void
    {
        $this->records = [self::record(false)];
        $period = new QuarterPeriod(2026, 4);
        $view = $this->show->execute(self::OWNER, null, $period);
        $this->expectException(InvalidQuarter::class);
        $this->change->execute(self::OWNER, null, $period, null, true, 0, $view->calculation->fingerprint);
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function forbiddenReaders(): iterable
    {
        yield 'other firm' => [self::OTHER, self::CLIENT];
        yield 'superadmin' => [self::ROOT, self::CLIENT];
        yield 'client on firm route' => [self::OWNER, self::CLIENT];
        yield 'staff on owner route' => [self::STAFF, null];
    }

    #[DataProvider('forbiddenReaders')]
    public function testUnauthorizedReadersCannotSeeFigures(string $actor, ?string $client): void
    {
        $this->firm = self::FIRM;
        $this->expectException(QuarterNotFound::class);
        $this->show->execute($actor, $client, new QuarterPeriod(2026, 4));
    }

    public function testOwnerKeepsPersonalHistoryAndStaffCanOnlyReadIt(): void
    {
        $period = new QuarterPeriod(2026, 4);
        $view = $this->show->execute(self::OWNER, null, $period);
        $this->change->execute(self::OWNER, null, $period, null, true, 0, $view->calculation->fingerprint);
        $this->firm = self::FIRM;
        $personal = $this->show->execute(self::STAFF, self::CLIENT, $period, 'personal');
        self::assertFalse($personal->access->mayManage(null, $personal->record));
        $this->change->execute(self::OWNER, null, $period, 'personal', false, 1);
        $view = $this->show->execute(self::OWNER, null, $period, 'personal');
        $this->change->execute(self::OWNER, null, $period, 'personal', true, 2, $view->calculation->fingerprint);
        $this->expectException(QuarterNotFound::class);
        $this->change->execute(self::STAFF, self::CLIENT, $period, 'personal', false, 3);
    }

    public function testAdminAndAdvisorCanManageFirmQuarterButOwnerCannot(): void
    {
        $this->firm = self::FIRM;
        $period = new QuarterPeriod(2026, 4);
        $view = $this->show->execute(self::STAFF, self::CLIENT, $period);
        $this->change->execute(self::STAFF, self::CLIENT, $period, null, true, 0, $view->calculation->fingerprint);
        $this->change->execute(self::ADVISOR, self::CLIENT, $period, null, false, 1);
        $this->expectException(QuarterNotFound::class);
        $this->change->execute(self::OWNER, null, $period, null, true, 2, $view->calculation->fingerprint);
    }

    public function testStaleVersionCannotReopen(): void
    {
        $period = new QuarterPeriod(2026, 4);
        $view = $this->show->execute(self::OWNER, null, $period);
        $this->change->execute(self::OWNER, null, $period, null, true, 0, $view->calculation->fingerprint);
        $this->expectException(QuarterChanged::class);
        $this->change->execute(self::OWNER, null, $period, null, false, 0);
    }

    public function testGuardChecksOriginalAndDestinationDatesAndScope(): void
    {
        $period = new QuarterPeriod(2026, 4);
        $view = $this->show->execute(self::OWNER, null, $period);
        $this->change->execute(self::OWNER, null, $period, null, true, 0, $view->calculation->fingerprint);
        $this->guard->assertOpen(self::OWNER, self::FIRM, new \DateTimeImmutable('2026-11-01'));
        $this->guard->assertOpen(self::OWNER, null, null, new \DateTimeImmutable('2026-09-30'));
        $this->expectException(ClosedPeriod::class);
        $this->guard->assertOpen(self::OWNER, null, new \DateTimeImmutable('2026-09-30'), new \DateTimeImmutable('2026-10-01'));
    }

    private static function record(bool $included): FinancialRecord
    {
        return new FinancialRecord('record', new \DateTimeImmutable('2026-11-01'), $included ? 'submitted' : 'draft', $included, true, 'Ejemplo', 10000, 'iva', 2100, 0, 0, 12100, 1);
    }
}
