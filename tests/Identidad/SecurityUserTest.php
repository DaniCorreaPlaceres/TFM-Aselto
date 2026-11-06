<?php

namespace App\Tests\Identidad;

use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Security\SecurityUser;
use PHPUnit\Framework\TestCase;

final class SecurityUserTest extends TestCase
{
    public function testEachRoleGetsItsSecurityRole(): void
    {
        $roles = [
            [Role::Superadmin, 'ROLE_SUPERADMIN', null],
            [Role::FirmAdmin, 'ROLE_FIRM_ADMIN', '00000000-0000-4000-8000-000000000001'],
            [Role::Advisor, 'ROLE_ADVISOR', '00000000-0000-4000-8000-000000000001'],
            [Role::Client, 'ROLE_CLIENT', '00000000-0000-4000-8000-000000000001'],
        ];

        foreach ($roles as [$role, $securityRole, $firmId]) {
            $user = SecurityUser::fromDomain(new User(
                '00000000-0000-4000-8000-000000000011',
                'persona@example.test',
                'hash',
                $role,
                $firmId,
            ));

            self::assertSame(['ROLE_USER', $securityRole], $user->getRoles());
        }
    }
}
