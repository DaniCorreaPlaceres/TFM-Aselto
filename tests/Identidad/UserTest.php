<?php

namespace App\Tests\Identidad;

use App\Identidad\Domain\InvalidUser;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testSuperadminHasNoFirm(): void
    {
        $user = new User(self::ID, 'Superadmin@example.test', 'hash', Role::Superadmin, null);

        self::assertSame('superadmin@example.test', $user->email());
        self::assertNull($user->firmId());
    }

    public function testFirmRoleRequiresAFirm(): void
    {
        $user = new User(self::ID, 'gestor@example.test', 'hash', Role::Advisor, self::FIRM_ID);

        self::assertSame(self::FIRM_ID, $user->firmId());
    }

    public function testSuperadminCannotBelongToAFirm(): void
    {
        $this->expectException(InvalidUser::class);

        new User(self::ID, 'superadmin@example.test', 'hash', Role::Superadmin, self::FIRM_ID);
    }

    public function testClientCanWorkWithoutAFirm(): void
    {
        $user = new User(self::ID, 'cliente@example.test', 'hash', Role::Client, null);

        self::assertNull($user->firmId());
    }

    public function testClientCanBelongToAFirm(): void
    {
        $user = new User(self::ID, 'cliente@example.test', 'hash', Role::Client, self::FIRM_ID);

        self::assertSame(self::FIRM_ID, $user->firmId());
    }

    public function testAdvisorRequiresAFirm(): void
    {
        $this->expectException(InvalidUser::class);

        new User(self::ID, 'gestor@example.test', 'hash', Role::Advisor, null);
    }

    private const ID = '00000000-0000-4000-8000-000000000011';
    private const FIRM_ID = '00000000-0000-4000-8000-000000000001';
}
