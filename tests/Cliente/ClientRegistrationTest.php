<?php

namespace App\Tests\Cliente;

use App\Asesoria\Domain\Firm;
use App\Asesoria\Domain\FirmRepository;
use App\Cliente\Application\ListClients;
use App\Cliente\Application\RegisterClient;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\ClientRepository;
use App\Cliente\Domain\InvalidClient;
use App\Cliente\Domain\MissingFirm;
use App\Identidad\Application\IssueAccessToken;
use App\Identidad\Application\StartAccount;
use App\Identidad\Domain\AccessPurpose;
use App\Identidad\Domain\AccessToken;
use App\Identidad\Domain\AccessTokenRepository;
use App\Identidad\Domain\AccountNotifier;
use App\Identidad\Domain\EmailAlreadyUsed;
use App\Identidad\Domain\PasswordHasher;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\TokenGenerator;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\FiscalContact;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\InvalidFiscalContact;
use App\Shared\Domain\Transaction;
use PHPUnit\Framework\TestCase;

final class ClientRegistrationTest extends TestCase
{
    public function testAVisitorRegistersWithoutAFirm(): void
    {
        $users = new MemoryUserRepository();
        $clients = new MemoryClientRepository();

        $client = $this->useCase(new MemoryFirmRepository(), $users, $clients)
            ->execute('  Ana  ', ClientKind::SelfEmployed, 'Ana@Example.test', null, $this->contact());

        self::assertSame('Ana', $client->name());
        self::assertSame('00000000T', $client->contact()->taxId());
        self::assertSame('600000000', $client->contact()->phone());
        self::assertSame('Madrid', $client->contact()->province());
        self::assertSame(ClientKind::SelfEmployed, $client->kind());
        self::assertNull($client->firmId());
        $user = $users->findByEmail('ana@example.test');
        self::assertInstanceOf(User::class, $user);
        self::assertSame(Role::Client, $user->role());
        self::assertNull($user->firmId());
        self::assertFalse($user->isActivated());
    }

    public function testAnEmptyNameIsRejected(): void
    {
        $this->expectException(InvalidClient::class);

        $this->useCase(new MemoryFirmRepository(), new MemoryUserRepository(), new MemoryClientRepository())
            ->execute('   ', ClientKind::SelfEmployed, 'ana@example.test', null, $this->contact());
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
            ->execute('Ana', ClientKind::SelfEmployed, 'ana@example.test', null, $this->contact());
    }

    public function testAFirmAdminRegistersAClientOfThatFirm(): void
    {
        $firms = new MemoryFirmRepository();
        $firm = new Firm('00000000-0000-4000-8000-000000000001', 'Norte');
        $firms->save($firm);
        $users = new MemoryUserRepository();
        $clients = new MemoryClientRepository();

        $client = $this->useCase($firms, $users, $clients)
            ->execute('Taller Sur', ClientKind::Company, 'sur@example.test', $firm->id(), $this->contact('B12345674'));

        self::assertSame($firm->id(), $client->firmId());
        $adminId = '00000000-0000-4000-8000-000000000090';
        $rootId = '00000000-0000-4000-8000-000000000091';
        $users->save(new User($adminId, 'admin-list@example.test', 'hash', Role::FirmAdmin, $firm->id(), true));
        $users->save(new User($rootId, 'root-list@example.test', 'hash', Role::Superadmin, null, true));
        $listings = (new ListClients($clients, $users))->execute($adminId);
        self::assertCount(1, $listings);
        self::assertSame('sur@example.test', $listings[0]->email());
        self::assertSame([], (new ListClients($clients, $users))->execute($rootId));
    }

    public function testAMissingFirmIsRejected(): void
    {
        $this->expectException(MissingFirm::class);

        $this->useCase(new MemoryFirmRepository(), new MemoryUserRepository(), new MemoryClientRepository())
            ->execute('Ana', ClientKind::SelfEmployed, 'ana@example.test', '00000000-0000-4000-8000-000000000001', $this->contact());
    }

    public function testAnInvalidTaxIdIsRejectedBeforeCreatingTheAccount(): void
    {
        $users = new MemoryUserRepository();

        try {
            $this->useCase(new MemoryFirmRepository(), $users, new MemoryClientRepository())
                ->execute('Ana', ClientKind::SelfEmployed, 'ana@example.test', null, $this->contact('00000000A'));
            self::fail('An invalid tax id should not register the client.');
        } catch (InvalidFiscalContact $exception) {
            self::assertSame(InvalidFiscalContact::TAX_ID, $exception->field());
            self::assertNull($users->findByEmail('ana@example.test'));
        }
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
            ->execute('Ana', ClientKind::SelfEmployed, 'ana@example.test', $north->id(), $this->contact());

        $adminId = '00000000-0000-4000-8000-000000000092';
        $users->save(new User($adminId, 'south-list@example.test', 'hash', Role::FirmAdmin, $south->id(), true));
        self::assertSame([], (new ListClients($clients, $users))->execute($adminId));
    }

    private function contact(string $taxId = '00000000T'): FiscalContact
    {
        return FiscalContact::required($taxId, '600 000 000', 'Calle Mayor 1', '28001', 'Madrid', 'Madrid');
    }

    private function useCase(FirmRepository $firms, UserRepository $users, ClientRepository $clients): RegisterClient
    {
        $tokens = new MemoryAccessTokenRepository();
        $accounts = new StartAccount(
            $users,
            new FakePasswordHasher(),
            new FakeIdGenerator(),
            new FakeTokenGenerator(),
            new IssueAccessToken($tokens, new FakeIdGenerator(), new FakeTokenGenerator(), new FixedClock()),
            new ImmediateTransaction(),
            new RecordingNotifier(),
        );

        return new RegisterClient($clients, $firms, new FakeIdGenerator(), $accounts);
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

    public function find(string $id): ?Client
    {
        foreach ($this->clients as $client) {
            if ($client->id() === $id) {
                return $client;
            }
        }

        return null;
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

final class MemoryAccessTokenRepository implements AccessTokenRepository
{
    /** @var array<string, AccessToken> */
    private array $tokens = [];

    public function findByHash(string $tokenHash): ?AccessToken
    {
        return $this->tokens[$tokenHash] ?? null;
    }

    public function discardOpen(string $userId, AccessPurpose $purpose): void
    {
        foreach ($this->tokens as $hash => $token) {
            if ($token->userId() === $userId && $token->purpose() === $purpose && null === $token->usedAt()) {
                unset($this->tokens[$hash]);
            }
        }
    }

    public function save(AccessToken $token): void
    {
        $this->tokens[$token->tokenHash()] = $token;
    }
}

final class FakeTokenGenerator implements TokenGenerator
{
    private int $sequence = 1;

    public function next(): string
    {
        return hash('sha256', 'token-'.$this->sequence++);
    }
}

final class FixedClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-11-07 00:00:00 UTC');
    }
}

final class RecordingNotifier implements AccountNotifier
{
    public function sendActivation(string $email, string $token): void
    {
    }

    public function sendPasswordReset(string $email, string $token): void
    {
    }
}
