<?php

namespace App\Finanzas\Application;

use App\Finanzas\Domain\FinancialRecords;
use App\Finanzas\Domain\QuarterCalculation;
use App\Finanzas\Domain\QuarterPeriod;
use App\Finanzas\Domain\QuarterRepository;
use App\Shared\Domain\Clock;

final class ShowQuarter
{
    public function __construct(private readonly QuarterAccess $access, private readonly QuarterRepository $quarters, private readonly FinancialRecords $records, private readonly Clock $clock)
    {
    }

    public function currentPeriod(): QuarterPeriod
    {
        return QuarterPeriod::forDate($this->clock->now()->setTimezone(new \DateTimeZone('Atlantic/Canary')));
    }

    public function execute(string $actorId, ?string $clientId, QuarterPeriod $period, ?string $scope = null): QuarterView
    {
        $access = $this->access->resolve($actorId, $clientId);
        $history = array_values(array_filter($this->quarters->forOwner($access->ownerId), $access->mayRead(...)));
        $firmId = $this->access->scope($access, $scope, $history);

        return new QuarterView($access, $firmId, $period, $this->quarters->find($access->ownerId, $firmId, $period), new QuarterCalculation($access->ownerId, $firmId, $period, $this->records->forClient($access->ownerId, $firmId)), $history);
    }
}
