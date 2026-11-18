<?php

namespace App\Tests\Shared;

use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Application\ManageLicenses;
use App\Shared\Domain\Clock;
use App\Shared\Domain\LicensePlan;
use App\Shared\Domain\LicenseRepository;
use App\Shared\Domain\LicenseUnavailable;
use PHPUnit\Framework\TestCase;

final class ManageLicensesTest extends TestCase
{
    public function testClientCannotAssignLicensesOrReadAdministrativeList(): void
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('find')->willReturn(new User('00000000-0000-4000-8000-000000000001', 'synthetic@example.test', 'synthetic-hash', Role::Client, null, true));
        $licenses = $this->createMock(LicenseRepository::class);
        $licenses->expects(self::never())->method('assign');
        $service = new ManageLicenses($users, $licenses, $this->createStub(Clock::class));
        $this->expectException(LicenseUnavailable::class);
        $service->assign('00000000-0000-4000-8000-000000000001', 'client', '00000000-0000-4000-8000-000000000001', LicensePlan::Professional);
    }

    public function testSuperadminHasNoPersonalConsumptionView(): void
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('find')->willReturn(new User('00000000-0000-4000-8000-000000000001', 'synthetic@example.test', 'synthetic-hash', Role::Superadmin, null, true));
        $licenses = $this->createMock(LicenseRepository::class);
        $licenses->expects(self::never())->method('quota');
        $service = new ManageLicenses($users, $licenses, $this->createStub(Clock::class));
        $this->expectException(LicenseUnavailable::class);
        $service->own('00000000-0000-4000-8000-000000000001');
    }
}
