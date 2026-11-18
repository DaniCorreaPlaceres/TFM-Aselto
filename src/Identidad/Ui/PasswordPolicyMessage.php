<?php

namespace App\Identidad\Ui;

use App\Identidad\Domain\PasswordRejected;
use App\Identidad\Domain\PasswordTooShort;

final class PasswordPolicyMessage
{
    public static function for(PasswordTooShort|PasswordRejected $error): string
    {
        if ($error instanceof PasswordTooShort) {
            return 'La contraseña debe tener al menos 15 caracteres. Puedes usar una frase larga.';
        }

        return match ($error->reason) {
            PasswordRejected::COMMON => 'Esta contraseña es demasiado común. Elige otra frase o una contraseña generada por tu gestor.',
            PasswordRejected::TOO_LONG => 'La contraseña no puede superar los 128 caracteres.',
            default => 'La contraseña contiene caracteres que no se pueden leer. Vuelve a escribirla.',
        };
    }
}
