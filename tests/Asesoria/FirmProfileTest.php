<?php

namespace App\Tests\Asesoria;

use App\Asesoria\Application\OpenFirmProfile;
use App\Asesoria\Application\SaveFirmProfile;
use App\Asesoria\Domain\Firm;
use App\Asesoria\Domain\FirmProfileDenied;
use App\Asesoria\Domain\FirmRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\FiscalContact;
use App\Shared\Domain\Transaction;
use PHPUnit\Framework\TestCase;

final class FirmProfileTest extends TestCase
{
    private const FIRM = '00000000-0000-4000-8000-000000000001';

    private const ADMIN = '00000000-0000-4000-8000-000000000012';

    private const ADVISOR = '00000000-0000-4000-8000-000000000013';

    public function testAFirmAdminSavesTheFirmContact(): void
    {
        [$firms, $users] = $this->store();

        (new SaveFirmProfile(new OpenFirmProfile($firms, $users), $firms, new FirmProfileTransaction()))
            ->execute(self::ADMIN, FiscalContact::required('B12345674', '910000000', 'Calle Mayor 1', '28013', 'Madrid', 'Madrid'));

        $firm = $firms->find(self::FIRM);
        self::assertInstanceOf(Firm::class, $firm);
        self::assertSame('B12345674', $firm->contact()->taxId());
        self::assertSame('28013', $firm->contact()->postalCode());
    }

    public function testAnAdvisorCannotOpenTheFirmProfile(): void
    {
        [$firms, $users] = $this->store();

        $this->expectException(FirmProfileDenied::class);

        (new OpenFirmProfile($firms, $users))->execute(self::ADVISOR);
    }

    /**
     * @return array{0: FirmProfileRepository, 1: FirmProfileUserRepository}
     */
    private function store(): array
    {
        $firms = new FirmProfileRepository();
        $firms->save(new Firm(self::FIRM, 'Asesoría de prueba'));

        $users = new FirmProfileUserRepository();
        $users->save(new User(self::ADMIN, 'admin@example.test', 'hash', Role::FirmAdmin, self::FIRM, true));
        $users->save(new User(self::ADVISOR, 'gestor@example.test', 'hash', Role::Advisor, self::FIRM, true));

        return [$firms, $users];
    }
}

final class FirmProfileRepository implements FirmRepository
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

final class FirmProfileUserRepository implements UserRepository
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

final class FirmProfileTransaction implements Transaction
{
    public function run(callable $operation): void
    {
        $operation();
    }
}
