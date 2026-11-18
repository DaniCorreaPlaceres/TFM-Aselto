<?php

namespace App\Cliente\Application;

final class ClientFirmContact
{
    public function __construct(public readonly string $name, public readonly ?string $email, public readonly string $phone, public readonly string $street, public readonly string $postalCode, public readonly string $municipality, public readonly string $province)
    {
    }
}
