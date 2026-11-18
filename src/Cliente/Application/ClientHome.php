<?php

namespace App\Cliente\Application;

final class ClientHome
{
    public function __construct(
        private readonly ?string $name,
        private readonly ?string $firmName,
    ) {
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function firmName(): ?string
    {
        return $this->firmName;
    }
}
