<?php

namespace App\Tests\Cliente;

use App\Asesoria\Domain\Firm;
use App\Asesoria\Domain\FirmRepository;
use App\Cliente\Application\ShowClientFirmContact;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\ClientRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\FiscalContact;
use PHPUnit\Framework\TestCase;

final class ClientFirmContactTest extends TestCase
{
    private const OWNER = '00000000-0000-4000-8000-000000000014';
    private const FIRM = '00000000-0000-4000-8000-000000000001';
    private const OTHER = '00000000-0000-4000-8000-000000000002';

    public function testOnlyLinkedFirmContactAndItsActiveAdminAreReturned(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('find')->willReturn(new User(self::OWNER, 'client@example.test', 'synthetic', Role::Client, self::FIRM, true));
        $users->expects(self::once())->method('findByFirm')->with(self::FIRM)->willReturn([
            new User(self::OTHER, 'foreign@example.test', 'synthetic', Role::FirmAdmin, self::OTHER, true),
            new User('00000000-0000-4000-8000-000000000003', 'inactive@example.test', 'synthetic', Role::FirmAdmin, self::FIRM),
            new User('00000000-0000-4000-8000-000000000004', 'admin@example.test', 'synthetic', Role::FirmAdmin, self::FIRM, true),
        ]);
        $clients = $this->createStub(ClientRepository::class);
        $clients->method('findByUserId')->willReturn($this->client(self::FIRM));
        $firms = $this->createMock(FirmRepository::class);
        $firms->expects(self::once())->method('find')->with(self::FIRM)->willReturn(new Firm(self::FIRM, 'Asesoría de ejemplo', FiscalContact::required('B12345674', '600000000', 'Dirección ficticia 1', '28001', 'Municipio de ejemplo', 'Madrid')));
        $contact = new ShowClientFirmContact($users, $clients, $firms)->execute(self::OWNER);
        self::assertNotNull($contact);
        self::assertSame('Asesoría de ejemplo', $contact->name);
        self::assertSame('admin@example.test', $contact->email);
        self::assertSame('600000000', $contact->phone);
        self::assertSame('Dirección ficticia 1', $contact->street);
    }

    public function testUnlinkedInactiveWrongRoleAndInconsistentFirmCannotReadContact(): void
    {
        foreach ([[Role::Client, true, null, null], [Role::Client, false, self::FIRM, self::FIRM], [Role::Advisor, true, self::FIRM, self::FIRM], [Role::Client, true, self::OTHER, self::FIRM]] as [$role, $active, $userFirm, $clientFirm]) {
            $users = $this->createStub(UserRepository::class);
            $users->method('find')->willReturn(new User(self::OWNER, 'client@example.test', 'synthetic', $role, $userFirm, $active));
            $clients = $this->createStub(ClientRepository::class);
            $clients->method('findByUserId')->willReturn($this->client($clientFirm));
            $firms = $this->createMock(FirmRepository::class);
            $firms->expects(self::never())->method('find');
            self::assertNull(new ShowClientFirmContact($users, $clients, $firms)->execute(self::OWNER));
        }
    }

    public function testMissingContactDetailsDoNotInventAnEmailOrPhone(): void
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('find')->willReturn(new User(self::OWNER, 'client@example.test', 'synthetic', Role::Client, self::FIRM, true));
        $users->method('findByFirm')->willReturn([]);
        $clients = $this->createStub(ClientRepository::class);
        $clients->method('findByUserId')->willReturn($this->client(self::FIRM));
        $firms = $this->createStub(FirmRepository::class);
        $firms->method('find')->willReturn(new Firm(self::FIRM, 'Ejemplo'));
        $contact = new ShowClientFirmContact($users, $clients, $firms)->execute(self::OWNER);
        self::assertNotNull($contact);
        self::assertNull($contact->email);
        self::assertSame('', $contact->phone);
    }

    private function client(?string $firm): Client
    {
        return new Client('00000000-0000-4000-8000-000000000021', self::OWNER, ClientKind::SelfEmployed, 'Cliente ficticio', $firm);
    }
}
