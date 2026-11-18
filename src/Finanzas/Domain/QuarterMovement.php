<?php

namespace App\Finanzas\Domain;

final class QuarterMovement
{
    public function __construct(
        public readonly string $id,
        public readonly \DateTimeImmutable $date,
        public readonly bool $income,
        public readonly string $partyName,
        public readonly int $baseCents,
        public readonly string $tax,
        public readonly int $taxCents,
        public readonly int $surchargeCents,
        public readonly int $withholdingCents,
        public readonly int $totalCents,
        public readonly bool $taxExempt = false,
    ) {
        if ($taxExempt && (0 !== $taxCents || 0 !== $surchargeCents)) {
            throw new InvalidQuarter('Exempt movements cannot include indirect tax or surcharge.');
        }
        if (min($baseCents, $taxCents, $surchargeCents, $withholdingCents, $totalCents) < 0 || !in_array($tax, ['iva', 'igic', 'ipsi'], true)) {
            throw new InvalidQuarter('The movement amounts are not valid.');
        }
        if ($totalCents !== $baseCents + $taxCents + $surchargeCents - $withholdingCents || ($income && 0 !== $surchargeCents) || (!$income && 0 !== $withholdingCents)) {
            throw new InvalidQuarter('The movement amounts are inconsistent.');
        }
    }
}
