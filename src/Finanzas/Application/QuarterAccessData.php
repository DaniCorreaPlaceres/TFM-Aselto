<?php

namespace App\Finanzas\Application;

use App\Finanzas\Domain\Quarter;

final class QuarterAccessData
{
    public function __construct(public readonly string $ownerId, public readonly string $clientId, public readonly string $clientName, public readonly ?string $firmId, public readonly bool $owner)
    {
    }

    public function mayRead(Quarter $quarter): bool
    {
        return $quarter->ownerId === $this->ownerId && ($this->owner || null === $quarter->firmId || $quarter->firmId === $this->firmId);
    }

    public function mayManage(?string $firmId, ?Quarter $existing): bool
    {
        return $this->owner
            ? null === $firmId && (null === $this->firmId || null !== $existing)
            : null !== $firmId && $firmId === $this->firmId;
    }
}
