<?php

namespace App\Asesoria\Application;

final class FirmSummary
{
    public function __construct(
        private readonly string $name,
        private readonly string $adminEmail,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function adminEmail(): string
    {
        return $this->adminEmail;
    }
}
