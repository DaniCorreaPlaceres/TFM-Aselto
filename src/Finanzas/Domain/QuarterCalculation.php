<?php

namespace App\Finanzas\Domain;

final class QuarterCalculation
{
    public readonly QuarterSummary $summary;
    /** @var list<FinancialRecord> */
    public readonly array $pending;
    public readonly string $fingerprint;

    /** @param list<FinancialRecord> $records */
    public function __construct(string $ownerId, ?string $firmId, QuarterPeriod $period, array $records)
    {
        usort($records, static fn (FinancialRecord $left, FinancialRecord $right): int => strcmp($left->id, $right->id));
        $movements = [];
        $pending = [];
        $relevant = [];
        foreach ($records as $record) {
            if (null !== $record->date && !$period->contains($record->date)) {
                continue;
            }
            $relevant[] = $record;
            if (null === $record->date || !$record->included) {
                $pending[] = $record;
            } else {
                $movements[] = $record->movement();
            }
        }
        usort($movements, static fn (QuarterMovement $left, QuarterMovement $right): int => [$left->date->format('Y-m-d'), $left->id] <=> [$right->date->format('Y-m-d'), $right->id]);
        $this->summary = new QuarterSummary($movements);
        $this->pending = $pending;
        $this->fingerprint = hash('sha256', json_encode([$ownerId, $firmId, $period->key(), $relevant], JSON_THROW_ON_ERROR));
    }
}
