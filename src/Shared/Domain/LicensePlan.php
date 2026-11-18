<?php

namespace App\Shared\Domain;

enum LicensePlan: string
{
    case Free = 'free';
    case Professional = 'professional';
    case Advisory = 'advisory';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Gratis', self::Professional => 'Profesional', self::Advisory => 'Asesoría',
        };
    }
}
