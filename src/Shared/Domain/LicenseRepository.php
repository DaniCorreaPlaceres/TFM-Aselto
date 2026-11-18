<?php

namespace App\Shared\Domain;

interface LicenseRepository
{
    public function quota(string $ownerId, \DateTimeImmutable $at): MonthlyQuota;

    /** @return list<array{id: string, kind: string, name: string, plan: string}> */
    public function subjects(): array;

    public function assign(string $kind, string $id, LicensePlan $plan, string $actorId, \DateTimeImmutable $at): void;

    /** @return array{plan: string, clients: int, reference_cents: int} */
    public function firm(string $firmId): array;
}
