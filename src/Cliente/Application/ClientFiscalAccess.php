<?php

namespace App\Cliente\Application;

use App\Cliente\Domain\Client;

final class ClientFiscalAccess
{
    public function __construct(
        private readonly Client $client,
        private readonly string $email,
        private readonly bool $editable,
    ) {
    }

    public function client(): Client
    {
        return $this->client;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function editable(): bool
    {
        return $this->editable;
    }
}
