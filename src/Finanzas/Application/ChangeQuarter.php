<?php

namespace App\Finanzas\Application;

use App\Finanzas\Domain\FinancialRecords;
use App\Finanzas\Domain\InvalidQuarter;
use App\Finanzas\Domain\Quarter;
use App\Finanzas\Domain\QuarterCalculation;
use App\Finanzas\Domain\QuarterChanged;
use App\Finanzas\Domain\QuarterNotFound;
use App\Finanzas\Domain\QuarterPeriod;
use App\Finanzas\Domain\QuarterRepository;
use App\Shared\Domain\ClientTransaction;
use App\Shared\Domain\Clock;

final class ChangeQuarter
{
    public function __construct(private readonly QuarterAccess $access, private readonly QuarterRepository $quarters, private readonly FinancialRecords $records, private readonly ClientTransaction $transaction, private readonly Clock $clock)
    {
    }

    public function execute(string $actorId, ?string $clientId, QuarterPeriod $period, ?string $scope, bool $close, int $version, string $fingerprint = ''): Quarter
    {
        $ownerId = $this->access->resolve($actorId, $clientId)->ownerId;

        return $this->transaction->run($ownerId, function () use ($actorId, $clientId, $period, $scope, $close, $version, $fingerprint): Quarter {
            $access = $this->access->resolve($actorId, $clientId);
            $firmId = $this->access->scope($access, $scope, $this->quarters->forOwner($access->ownerId));
            $existing = $this->quarters->find($access->ownerId, $firmId, $period);
            if (!$access->mayManage($firmId, $existing)) {
                throw new QuarterNotFound('The quarter cannot be changed by this actor.');
            }
            if ((null === $existing ? 0 : $existing->version) !== $version) {
                throw new QuarterChanged('The quarter has changed.');
            }
            $at = $this->clock->now();
            if ($close) {
                $calculation = new QuarterCalculation($access->ownerId, $firmId, $period, $this->records->forClient($access->ownerId, $firmId));
                if ([] !== $calculation->pending) {
                    throw new InvalidQuarter('Pending or undated records prevent closing.');
                }
                if (!hash_equals($calculation->fingerprint, $fingerprint)) {
                    throw new QuarterChanged('The financial data has changed.');
                }
                $updated = null === $existing ? Quarter::firstClose($access->ownerId, $firmId, $period, $calculation->summary, $actorId, $at) : $existing->close($calculation->summary, $actorId, $at);
            } else {
                if (null === $existing) {
                    throw new QuarterChanged('The quarter is not closed.');
                }
                $updated = $existing->reopen($actorId, $at);
            }
            $this->quarters->save($updated);

            return $updated;
        });
    }
}
