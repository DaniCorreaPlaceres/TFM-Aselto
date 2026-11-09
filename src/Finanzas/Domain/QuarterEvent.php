<?php

namespace App\Finanzas\Domain;

final class QuarterEvent
{
    public function __construct(public readonly bool $closed, public readonly string $actorId, public readonly \DateTimeImmutable $at, public readonly QuarterSummary $snapshot)
    {
    }
}
