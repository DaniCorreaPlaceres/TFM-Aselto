<?php

namespace App\Identidad\Ui;

use App\Identidad\Domain\Role;

final class RoleLabel
{
    public static function inSpanish(Role $role): string
    {
        return match ($role) {
            Role::Superadmin => 'Superadmin',
            Role::FirmAdmin => 'Admin de la asesoría',
            Role::Advisor => 'Gestor',
            Role::Client => 'Cliente',
        };
    }
}
