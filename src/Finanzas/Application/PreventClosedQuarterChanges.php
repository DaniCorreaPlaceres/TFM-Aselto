<?php

namespace App\Finanzas\Application;

use App\Finanzas\Domain\QuarterPeriod;
use App\Finanzas\Domain\QuarterRepository;
use App\Shared\Domain\ClosedPeriod;
use App\Shared\Domain\ClosedPeriodGuard;

final class PreventClosedQuarterChanges implements ClosedPeriodGuard
{
    public function __construct(private readonly QuarterRepository $quarters)
    {
    }

    public function assertOpen(string $ownerId, ?string $firmId, ?\DateTimeImmutable ...$dates): void
    {
        $checked = [];
        foreach ($dates as $date) {
            if (null === $date) {
                continue;
            }
            $period = QuarterPeriod::forDate($date);
            if (isset($checked[$period->key()])) {
                continue;
            }
            $checked[$period->key()] = true;
            if ($this->quarters->find($ownerId, $firmId, $period)?->closed) {
                throw new ClosedPeriod('The quarter must be reopened before changing its records.');
            }
        }
    }
}
