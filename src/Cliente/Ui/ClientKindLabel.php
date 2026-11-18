<?php

namespace App\Cliente\Ui;

use App\Cliente\Domain\ClientKind;

final class ClientKindLabel
{
    public static function inSpanish(ClientKind $kind): string
    {
        return match ($kind) {
            ClientKind::SelfEmployed => 'Autónomo',
            ClientKind::Company => 'Sociedad',
        };
    }
}
