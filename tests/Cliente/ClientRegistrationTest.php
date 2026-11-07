<?php

namespace App\Tests\Cliente;

use App\Asesoria\Domain\Firm;
use App\Asesoria\Domain\FirmRepository;
use App\Cliente\Application\ListClients;
use App\Cliente\Application\RegisterClient;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\ClientRepository;
use App\Cliente\Domain\EmailAlreadyUsed;
use App\Cliente\Domain\InvalidClient;
use App\Cliente\Domain\MissingFirm;
use App\Cliente\Domain\PasswordTooShort;
use App\Identidad\Domain\PasswordHasher;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\Transaction;
use PHPUnit\Framework\TestCase;

final class ClientRegistrationTest extends TestCase
{
    public function testAVisitorRegistersWithoutAFirm(): void
    {
        $users = new MemoryUserRepository();
        $clients = new MemoryClientRepository();

        $client = $this->useCase(new MemoryFirmRepository(), $users, $clients)
            ->execute('  Ana  ', ClientKind::SelfEmployed, 'Ana@Example.test', 'asesoria1', null);

        self::assertSame('Ana', $client->name());
        self::assertSame(ClientKind::SelfEmployed, $client->kind());
        self::assertNull($client->firmId());
        $user = $users->findByEmail('ana@example.test');
        self::assertInstanceOf(User::class, $user);
        self::assertSame(Role::Client, $user->role());
        self::assertNull($user->firmId());
    }

    public function testAShortPasswordIsRejected(): void
    {
        $this->expectException(PasswordTooShort::class);

        $this->useCase(new MemoryFirmRepository(), new MemoryUserRepository(), new MemoryClientRepository())
            ->execute('Ana', ClientKind::Company, 'ana@example.test', 'corta', null);
    }

    public function testAnEmptyNameIsRejected(): void
    {
        $this->expectException(InvalidClient::class);

        $this->useCase(new MemoryFirmRepository(), new MemoryUserRepository(), new MemoryClientRepository())
            ->execute('   ', ClientKind::SelfEmployed, 'ana@example.test', 'asesoria1', null);
    }

    public function testARepeatedEmailIsRejected(): void
    {
        $users = new MemoryUserRepository();
        $users->save(new User(
            '00000000-0000-4000-8000-000000000099',
            'ana@example.test',
            'hash',
            Role::Client,
            null,
        ));

        $this->expectException(EmailAlreadyUsed::class);

        $this->useCase(new MemoryFirmRepository(), $users, new MemoryClientRepository())
            ->execute('Ana', ClientKind::SelfEmployed, 'ana@example.test', 'asesoria1', null);
    }

    public function testAFirmAdminRegistersAClientOfThatFirm(): void
    {
        $firms = new MemoryFirmRepository();
        $firm = new Firm('00000000-0000-4000-8000-000000000001', 'Norte');
        $firms->save($firm);
        $users = new MemoryUserRepository();
        $clients = new MemoryClientRepository();

        $client = $this->useCase($firms, $users, $clients)
            ->execute('Taller Sur', ClientKind::Company, 'sur@example.test', 'asesoria1', $firm->id());

        self::assertSame($firm->id(), $client->firmId());
        $listings = (new ListClients($clients, $users))->execute($firm->id());
        self::assertCount(1, $listings);
        self::assertSame('sur@example.test', $listings[0]->email());
        self::assertSame([], (new ListClients($clients, $users))->execute(null));
    }

    public function testAMissingFirmIsRejected(): void
    {
        $this->expectException(MissingFirm::class);

        $this->useCase(new MemoryFirmRepository(), new MemoryUserRepository(), new MemoryClientRepository())
            ->execute('Ana', ClientKind::SelfEmployed, 'ana@example.test', 'asesoria1', '00000000-0000-4000-8000-000000000001');
    }

    public function testAnotherFirmDoesNotSeeTheClient(): void
    {
        $firms = new MemoryFirmRepository();
        $north = new Firm('00000000-0000-4000-8000-000000000001', 'Norte');
        $south = new Firm('00000000-0000-4000-8000-000000000002', 'Sur');
        $firms->save($north);
        $firms->save($south);
        $users = new MemoryUserRepository();
        $clients = new MemoryClientRepository();
        $this->useCase($firms, $users, $clients)
            ->execute('Ana', ClientKind::SelfEmployed, 'ana@example.test', 'asesoria1', $north->id());

        self::assertSame([], (new ListClients($clients, $users))->execute($south->id()));
    }

    private function useCase(FirmRepository $firms, UserRepository $users, ClientRepository $clients): RegisterClient
    {
        return new RegisterClient($clients, $users, $firms, new FakePasswordHasher(), new FakeIdGenerator(), new ImmediateTransaction());
    }
}

final class MemoryFirmRepository implements FirmRepository
{
    /** @var array<string, Firm> */
    private array $firms = [];

    public function find(string $id): ?Firm
    {
        return $this->firms[$id] ?? null;
    }

    public function all(): array
    {
        return array_values($this->firms);
    }

    public function save(Firm $firm): void
    {
        $this->firms[$firm->id()] = $firm;
    }
}

final class MemoryClientRepository implements ClientRepository
{
    /** @var array<string, Client> */
    private array $clients = [];

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

final class MemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    private array $users = [];

    public function find(string $id): ?User
    {
        foreach ($this->users as $user) {
            if ($user->id() === $id) {
                return $user;
            }
        }

        return null;
    }

    public function findByEmail(string $email): ?User
    {
        return $this->users[mb_strtolower(trim($email))] ?? null;
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
        $this->users[$user->email()] = $user;
    }
}

final class FakePasswordHasher implements PasswordHasher
{
    public function hash(string $plainPassword): string
    {
        return 'hash:'.$plainPassword;
    }
}

final class FakeIdGenerator implements IdGenerator
{
    private int $sequence = 1;

    public function next(): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $this->sequence++);
    }
}

final class ImmediateTransaction implements Transaction
{
    public function run(callable $operation): void
    {
        $operation();
    }
}
