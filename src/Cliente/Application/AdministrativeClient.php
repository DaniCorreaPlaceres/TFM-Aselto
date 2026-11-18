<?php

namespace App\Cliente\Application;

use App\Cliente\Domain\Client;

final readonly class AdministrativeClient
{
    public function __construct(public Client $client, public string $email, public bool $activated)
    {
    }
}
