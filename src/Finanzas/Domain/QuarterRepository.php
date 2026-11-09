<?php

namespace App\Finanzas\Domain;

interface QuarterRepository
{
    public function find(string $ownerId, ?string $firmId, QuarterPeriod $period): ?Quarter;

    /** @return list<Quarter> */
    public function forOwner(string $ownerId): array;

    public function save(Quarter $quarter): void;
}
