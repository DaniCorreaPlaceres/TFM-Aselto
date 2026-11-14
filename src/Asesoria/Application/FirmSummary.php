<?php

namespace App\Asesoria\Application;

final class FirmSummary
{
    public function __construct(
        private readonly string $name,
        private readonly string $adminEmail,
        private readonly string $id,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function adminEmail(): string
    {
        return $this->adminEmail;
    }
}
