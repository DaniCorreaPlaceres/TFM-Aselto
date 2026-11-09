<?php

namespace App\Finanzas\Application;

use App\Finanzas\Domain\Quarter;
use App\Finanzas\Domain\QuarterCalculation;
use App\Finanzas\Domain\QuarterPeriod;

final class QuarterView
{
    /** @param list<Quarter> $history */
    public function __construct(public readonly QuarterAccessData $access, public readonly ?string $firmId, public readonly QuarterPeriod $period, public readonly ?Quarter $record, public readonly QuarterCalculation $calculation, public readonly array $history)
    {
    }
}
