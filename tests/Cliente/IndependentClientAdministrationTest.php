<?php

namespace App\Tests\Cliente;

use App\Cliente\Application\AdministerIndependentClients;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientChanged;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\ClientRepository;
use App\Cliente\Domain\UnknownClient;
use App\Identidad\Domain\AccountTransaction;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\ClientTransaction;
use App\Shared\Domain\FiscalContact;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IndependentClientAdministrationTest extends TestCase
{
    private const ACTOR = '00000000-0000-4000-8000-000000000001';
    private const OWNER = '00000000-0000-4000-8000-000000000002';
    private const CLIENT = '00000000-0000-4000-8000-000000000003';
    private const FIRM = '00000000-0000-4000-8000-000000000004';

    /** @return iterable<string, array{Role, bool, ?string, Role, ?string}> */
    public static function deniedCases(): iterable
    {
        yield 'inactive superadmin' => [Role::Superadmin, false, null, Role::Client, null];
        yield 'client actor' => [Role::Client, true, null, Role::Client, null];
        yield 'advisory admin actor' => [Role::FirmAdmin, true, null, Role::Client, null];
        yield 'advisor actor' => [Role::Advisor, true, null, Role::Client, null];
        yield 'linked client' => [Role::Superadmin, true, self::FIRM, Role::Client, self::FIRM];
        yield 'inconsistent membership' => [Role::Superadmin, true, null, Role::Client, self::FIRM];
        yield 'non-client owner' => [Role::Superadmin, true, null, Role::Superadmin, null];
    }

    #[DataProvider('deniedCases')]
    public function testAdministrativeAccessRequiresAnActiveSuperadminAndIndependentClient(Role $role, bool $active, ?string $profileFirm, Role $ownerRole, ?string $ownerFirm): void
    {
        $users = $this->createStub(UserRepository::class);
        $actor = new User(self::ACTOR, 'actor@example.test', 'hash', $role, in_array($role, [Role::FirmAdmin, Role::Advisor], true) ? self::FIRM : null, $active);
        $owner = new User(self::OWNER, 'owner@example.test', 'hash', $ownerRole, $ownerFirm, true);
        $users->method('find')->willReturnCallback(static fn (string $id): User => self::ACTOR === $id ? $actor : $owner);
        $clients = $this->createStub(ClientRepository::class);
        $clients->method('find')->willReturn(new Client(self::CLIENT, self::OWNER, ClientKind::SelfEmployed, 'Ficticio', $profileFirm));
        $service = new AdministerIndependentClients($clients, $users, $this->createStub(ClientTransaction::class), $this->createStub(AccountTransaction::class));
        $this->expectException(UnknownClient::class);
        $service->open(self::ACTOR, self::CLIENT);
    }

    public function testMembershipIsCheckedAgainAfterAcquiringTheLock(): void
    {
        $users = $this->users();
        $client = new Client(self::CLIENT, self::OWNER, ClientKind::SelfEmployed, 'Ficticio', null);
        $clients = $this->createMock(ClientRepository::class);
        // Return a linked profile on the second read, after the lock is acquired.
        $clients->method('find')->willReturnOnConsecutiveCalls($client, new Client(self::CLIENT, self::OWNER, ClientKind::SelfEmployed, 'Ficticio', self::FIRM));
        $clients->expects(self::never())->method('save');
        $transaction = $this->createStub(ClientTransaction::class);
        $transaction->method('run')->willReturnCallback(static fn (string $id, callable $operation): mixed => $operation());
        $accounts = $this->createStub(AccountTransaction::class);
        $accounts->method('forUser')->willReturnCallback(static function (string $id, callable $operation): void { $operation(); });
        $service = new AdministerIndependentClients($clients, $users, $transaction, $accounts);
        $this->expectException(UnknownClient::class);
        $service->save(self::ACTOR, self::CLIENT, 1, 'Nuevo', $this->contact());
    }

    public function testAnOldVersionCannotOverwriteTheProfile(): void
    {
        $clients = $this->createMock(ClientRepository::class);
        $clients->method('find')->willReturn(new Client(self::CLIENT, self::OWNER, ClientKind::Company, 'Ficticio', null, null, 2));
        $clients->expects(self::never())->method('save');
        $transaction = $this->createStub(ClientTransaction::class);
        $transaction->method('run')->willReturnCallback(static fn (string $id, callable $operation): mixed => $operation());
        $accounts = $this->createStub(AccountTransaction::class);
        $accounts->method('forUser')->willReturnCallback(static function (string $id, callable $operation): void { $operation(); });
        $this->expectException(ClientChanged::class);
        (new AdministerIndependentClients($clients, $this->users(), $transaction, $accounts))->save(self::ACTOR, self::CLIENT, 1, 'Nuevo', $this->contact());
    }

    private function users(): UserRepository
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('find')->willReturnCallback(static fn (string $id): User => new User($id, $id.'@example.test', 'hash', self::ACTOR === $id ? Role::Superadmin : Role::Client, null, true));

        return $users;
    }

    private function contact(): FiscalContact
    {
        return FiscalContact::required('00000000T', '600000000', 'Dirección ficticia 1', '28001', 'Municipio de ejemplo', 'Madrid');
    }
}
