<?php

namespace App\Shared\Domain;

final readonly class MonthlyQuota
{
    public function __construct(public LicensePlan $plan, public string $month, public int $used)
    {
    }

    public static function month(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('Atlantic/Canary'))->format('Y-m');
    }

    public function limit(): ?int
    {
        return LicensePlan::Free === $this->plan ? PlanCatalog::FREE_MONTHLY_DOCUMENTS : null;
    }

    public function assertAvailable(): void
    {
        if (null !== $this->limit() && $this->used >= $this->limit()) {
            throw new DocumentQuotaExceeded('Monthly document quota exhausted.');
        }
    }
}
