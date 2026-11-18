<?php

namespace App\Cliente\Application;

use App\Cliente\Domain\ClientKind;

final class ClientListing
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly ClientKind $kind,
        private readonly string $email,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function kind(): ClientKind
    {
        return $this->kind;
    }

    public function email(): string
    {
        return $this->email;
    }
}
