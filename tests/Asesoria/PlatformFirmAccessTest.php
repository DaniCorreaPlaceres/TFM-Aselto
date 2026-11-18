<?php

namespace App\Tests\Asesoria;

use App\Asesoria\Application\AdministerFirms;
use App\Asesoria\Application\ListFirms;
use App\Asesoria\Domain\FirmAdministrationTransaction;
use App\Asesoria\Domain\FirmProfileDenied;
use App\Asesoria\Domain\FirmRepository;
use App\Identidad\Domain\AccessTokenRepository;
use App\Identidad\Domain\AdminEmailChangeRepository;
use App\Identidad\Domain\EmailChangeNotifier;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\TokenGenerator;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\Clock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlatformFirmAccessTest extends TestCase
{
    private const USER = '00000000-0000-4000-8000-000000000001';
    private const FIRM = '00000000-0000-4000-8000-000000000002';

    /** @return iterable<string, array{Role, bool}> */
    public static function forbiddenActors(): iterable
    {
        yield 'client' => [Role::Client, true];
        yield 'advisor' => [Role::Advisor, true];
        yield 'firm admin' => [Role::FirmAdmin, true];
        yield 'inactive superadmin' => [Role::Superadmin, false];
    }

    #[DataProvider('forbiddenActors')]
    public function testListChecksActorBeforeQueryingFirms(Role $role, bool $active): void
    {
        $firms = $this->createMock(FirmRepository::class);
        $firms->expects(self::never())->method('all');
        $this->expectException(FirmProfileDenied::class);
        (new ListFirms($firms, $this->users($role, $active)))->execute(self::USER);
    }

    #[DataProvider('forbiddenActors')]
    public function testAdministrativeUseCaseChecksActorBeforeOpeningProfile(Role $role, bool $active): void
    {
        $firms = $this->createMock(FirmRepository::class);
        $firms->expects(self::never())->method('find');
        $service = new AdministerFirms($firms, $this->users($role, $active), $this->createStub(FirmAdministrationTransaction::class), $this->createStub(AdminEmailChangeRepository::class), $this->createStub(AccessTokenRepository::class), $this->createStub(TokenGenerator::class), $this->createStub(Clock::class), $this->createStub(EmailChangeNotifier::class));
        $this->expectException(FirmProfileDenied::class);
        $service->open(self::USER, self::FIRM);
    }

    private function users(Role $role, bool $active): UserRepository
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('find')->willReturn(new User(self::USER, 'actor@example.test', 'hash', $role, Role::Superadmin === $role ? null : self::FIRM, $active));

        return $users;
    }
}
