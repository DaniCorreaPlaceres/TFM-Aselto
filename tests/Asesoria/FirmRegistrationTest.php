<?php

namespace App\Tests\Asesoria;

use App\Asesoria\Application\CreateFirm;
use App\Asesoria\Application\InviteAdvisor;
use App\Asesoria\Domain\Firm;
use App\Asesoria\Domain\FirmRepository;
use App\Asesoria\Domain\InvalidFirm;
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

final class FirmRegistrationTest extends TestCase
{
    public function testFirmRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidFirm::class);

        new Firm('00000000-0000-4000-8000-000000000001', '   ');
    }

    public function testSuperadminCreatesAFirmAndItsAdmin(): void
    {
        $firms = new MemoryFirmRepository();
        $users = new MemoryUserRepository();
        $useCase = $this->useCase($firms, $users);

        $firm = $useCase->execute('  Norte  ', 'Admin@Example.test', $this->contact());

        self::assertSame('Norte', $firm->name());
        self::assertSame('B12345674', $firm->contact()->taxId());
        self::assertSame('35001', $firm->contact()->postalCode());
        self::assertSame('Las Palmas', $firm->contact()->province());
        $admin = $users->findByEmail('admin@example.test');
        self::assertInstanceOf(User::class, $admin);
        self::assertSame(Role::FirmAdmin, $admin->role());
        self::assertSame($firm->id(), $admin->firmId());
        self::assertFalse($admin->isActivated());
    }

    public function testARepeatedEmailIsRejected(): void
    {
        $users = new MemoryUserRepository();
        $users->save(new User(
            '00000000-0000-4000-8000-000000000099',
            'admin@example.test',
            'hash',
            Role::Client,
            null,
        ));

        $this->expectException(EmailAlreadyUsed::class);

        $this->useCase(new MemoryFirmRepository(), $users)
            ->execute('Norte', 'admin@example.test', $this->contact());
    }

    public function testAPostalCodeFromAnotherProvinceIsRejected(): void
    {
        $this->expectException(InvalidFiscalContact::class);

        FiscalContact::required('B12345674', '910000000', 'Calle Mayor 1', '28013', 'Madrid', 'Las Palmas');
    }

    public function testFirmAdminInvitesAnAdvisorOfTheSameFirm(): void
    {
        $firms = new MemoryFirmRepository();
        $firm = new Firm('00000000-0000-4000-8000-000000000001', 'Norte');
        $firms->save($firm);
        $users = new MemoryUserRepository();

        (new InviteAdvisor($firms, $this->accounts($users)))
            ->execute($firm->id(), 'gestor@example.test');

        $advisor = $users->findByEmail('gestor@example.test');
        self::assertInstanceOf(User::class, $advisor);
        self::assertSame(Role::Advisor, $advisor->role());
        self::assertSame($firm->id(), $advisor->firmId());
        self::assertFalse($advisor->isActivated());
    }

    public function testAnAdvisorCannotBeInvitedToAMissingFirm(): void
    {
        $this->expectException(InvalidFirm::class);

        (new InviteAdvisor(new MemoryFirmRepository(), $this->accounts(new MemoryUserRepository())))
            ->execute('00000000-0000-4000-8000-000000000001', 'gestor@example.test');
    }

    private function contact(): FiscalContact
    {
        return FiscalContact::required('B12345674', '+34 928 000 000', 'Calle Mayor 1', '35001', 'Las Palmas de Gran Canaria', 'Las Palmas');
    }

    private function useCase(FirmRepository $firms, UserRepository $users): CreateFirm
    {
        return new CreateFirm($firms, new FakeIdGenerator(), $this->accounts($users));
    }

    private function accounts(UserRepository $users): StartAccount
    {
        return new StartAccount(
            $users,
            new FakePasswordHasher(),
            new FakeIdGenerator(),
            new FakeTokenGenerator(),
            new IssueAccessToken(new MemoryAccessTokenRepository(), new FakeIdGenerator(), new FakeTokenGenerator(), new FixedClock()),
            new ImmediateTransaction(),
            new RecordingNotifier(),
        );
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
