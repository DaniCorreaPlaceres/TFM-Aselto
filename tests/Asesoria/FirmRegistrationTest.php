<?php

namespace App\Tests\Asesoria;

use App\Asesoria\Application\CreateFirm;
use App\Asesoria\Application\InviteAdvisor;
use App\Asesoria\Domain\EmailAlreadyUsed;
use App\Asesoria\Domain\Firm;
use App\Asesoria\Domain\FirmRepository;
use App\Asesoria\Domain\InvalidFirm;
use App\Asesoria\Domain\PasswordTooShort;
use App\Identidad\Domain\PasswordHasher;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\IdGenerator;
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

        $firm = $useCase->execute('  Norte  ', 'Admin@Example.test', 'asesoria1');

        self::assertSame('Norte', $firm->name());
        $admin = $users->findByEmail('admin@example.test');
        self::assertInstanceOf(User::class, $admin);
        self::assertSame(Role::FirmAdmin, $admin->role());
        self::assertSame($firm->id(), $admin->firmId());
    }

    public function testAShortPasswordIsRejected(): void
    {
        $this->expectException(PasswordTooShort::class);

        $this->useCase(new MemoryFirmRepository(), new MemoryUserRepository())
            ->execute('Norte', 'admin@example.test', 'corta');
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
            ->execute('Norte', 'admin@example.test', 'asesoria1');
    }

    public function testFirmAdminInvitesAnAdvisorOfTheSameFirm(): void
    {
        $firms = new MemoryFirmRepository();
        $firm = new Firm('00000000-0000-4000-8000-000000000001', 'Norte');
        $firms->save($firm);
        $users = new MemoryUserRepository();

        (new InviteAdvisor($firms, $users, new FakePasswordHasher(), new FakeIdGenerator(), new ImmediateTransaction()))
            ->execute($firm->id(), 'gestor@example.test', 'asesoria1');

        $advisor = $users->findByEmail('gestor@example.test');
        self::assertInstanceOf(User::class, $advisor);
        self::assertSame(Role::Advisor, $advisor->role());
        self::assertSame($firm->id(), $advisor->firmId());
    }

    public function testAnAdvisorCannotBeInvitedToAMissingFirm(): void
    {
        $this->expectException(InvalidFirm::class);

        (new InviteAdvisor(
            new MemoryFirmRepository(),
            new MemoryUserRepository(),
            new FakePasswordHasher(),
            new FakeIdGenerator(),
            new ImmediateTransaction(),
        ))->execute('00000000-0000-4000-8000-000000000001', 'gestor@example.test', 'asesoria1');
    }

    private function useCase(FirmRepository $firms, UserRepository $users): CreateFirm
    {
        return new CreateFirm($firms, $users, new FakePasswordHasher(), new FakeIdGenerator(), new ImmediateTransaction());
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
