<?php

namespace App\Tests\Cliente;

use App\Cliente\Application\OpenClientFiscalProfile;
use App\Cliente\Application\SaveClientFiscalProfile;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\ClientProfileLocked;
use App\Cliente\Domain\ClientRepository;
use App\Cliente\Domain\UnknownClient;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\ClientTransaction;
use App\Shared\Domain\FiscalContact;
use PHPUnit\Framework\TestCase;

final class ClientFiscalProfileTest extends TestCase
{
    private const FIRM = '00000000-0000-4000-8000-000000000001';

    private const OTHER_FIRM = '00000000-0000-4000-8000-000000000002';

    private const CLIENT_ID = '00000000-0000-4000-8000-000000000021';

    private const CLIENT_USER = '00000000-0000-4000-8000-000000000014';

    private const ADMIN = '00000000-0000-4000-8000-000000000012';

    private const ADVISOR = '00000000-0000-4000-8000-000000000013';

    public function testAClientSavesTheirOwnFiscalData(): void
    {
        [$clients, $users] = $this->store();
        $contact = $this->contact('00000000T');

        (new SaveClientFiscalProfile(new OpenClientFiscalProfile($clients, $users), $clients, new ProfileTransaction()))
            ->own(self::CLIENT_USER, $contact);

        $saved = $clients->find(self::CLIENT_ID);
        self::assertInstanceOf(Client::class, $saved);
        self::assertSame('00000000T', $saved->contact()->taxId());
        self::assertSame('28001', $saved->contact()->postalCode());
    }

    public function testAFirmAdminSavesAClientOfThatFirm(): void
    {
        [$clients, $users] = $this->store(self::FIRM);
        $contact = $this->contact('B12345674');

        (new SaveClientFiscalProfile(new OpenClientFiscalProfile($clients, $users), $clients, new ProfileTransaction()))
            ->byActor(self::ADMIN, self::CLIENT_ID, $contact);

        self::assertSame('B12345674', $clients->find(self::CLIENT_ID)?->contact()->taxId());
    }

    public function testAnAdvisorCanOpenButNotSave(): void
    {
        [$clients, $users] = $this->store(self::FIRM);
        $access = (new OpenClientFiscalProfile($clients, $users))->forActor(self::ADVISOR, self::CLIENT_ID);

        self::assertFalse($access->editable());
        self::assertSame('cliente@example.test', $access->email());

        $this->expectException(ClientProfileLocked::class);

        (new SaveClientFiscalProfile(new OpenClientFiscalProfile($clients, $users), $clients, new ProfileTransaction()))
            ->byActor(self::ADVISOR, self::CLIENT_ID, $this->contact());
    }

    public function testAnotherFirmCannotOpenTheClient(): void
    {
        [$clients, $users] = $this->store(self::FIRM);
        $users->save(new User('00000000-0000-4000-8000-000000000099', 'otro@example.test', 'hash', Role::FirmAdmin, self::OTHER_FIRM, true));

        $this->expectException(UnknownClient::class);

        (new OpenClientFiscalProfile($clients, $users))
            ->forActor('00000000-0000-4000-8000-000000000099', self::CLIENT_ID);
    }

    /**
     * @return array{0: ProfileClientRepository, 1: ProfileUserRepository}
     */
    private function store(?string $firmId = null): array
    {
        $clients = new ProfileClientRepository();
        $clients->save(new Client(
            self::CLIENT_ID,
            self::CLIENT_USER,
            ClientKind::SelfEmployed,
            'Ana',
            $firmId,
        ));

        $users = new ProfileUserRepository();
        $users->save(new User(self::CLIENT_USER, 'cliente@example.test', 'hash', Role::Client, $firmId, true));
        $users->save(new User(self::ADMIN, 'admin@example.test', 'hash', Role::FirmAdmin, self::FIRM, true));
        $users->save(new User(self::ADVISOR, 'gestor@example.test', 'hash', Role::Advisor, self::FIRM, true));

        return [$clients, $users];
    }

    private function contact(string $taxId = '00000000T'): FiscalContact
    {
        return FiscalContact::required($taxId, '600000000', 'Calle Mayor 1', '28001', 'Madrid', 'Madrid');
    }
}

final class ProfileClientRepository implements ClientRepository
{
    /** @var array<string, Client> */
    private array $clients = [];

    public function find(string $id): ?Client
    {
        return $this->clients[$id] ?? null;
    }

    public function findByUserId(string $userId): ?Client
    {
        foreach ($this->clients as $client) {
            if ($client->userId() === $userId) {
                return $client;
            }
        }

        return null;
    }

    public function findByFirm(string $firmId): array
    {
        return array_values(array_filter(
            $this->clients,
            static fn (Client $client): bool => $client->firmId() === $firmId,
        ));
    }

    public function findWithoutFirm(): array
    {
        return array_values(array_filter(
            $this->clients,
            static fn (Client $client): bool => null === $client->firmId(),
        ));
    }

    public function save(Client $client): void
    {
        $this->clients[$client->id()] = $client;
    }
}

final class ProfileUserRepository implements UserRepository
{
    /** @var array<string, User> */
    private array $users = [];

    public function find(string $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email() === mb_strtolower(trim($email))) {
                return $user;
            }
        }

        return null;
    }

    public function findByFirm(string $firmId): array
    {
        return array_values(array_filter(
            $this->users,
            static fn (User $user): bool => $user->firmId() === $firmId,
        ));
    }

    public function save(User $user): void
    {
        $this->users[$user->id()] = $user;
    }
}

final class ProfileTransaction implements ClientTransaction
{
    public function run(string $ownerId, callable $operation): mixed
    {
        return $operation();
    }
}
