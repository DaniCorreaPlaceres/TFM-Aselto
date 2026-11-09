<?php

namespace App\Shared\Domain;

interface ClosedPeriodGuard
{
    public function assertOpen(string $ownerId, ?string $firmId, ?\DateTimeImmutable ...$dates): void;
}
