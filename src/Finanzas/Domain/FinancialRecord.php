<?php

namespace App\Finanzas\Domain;

final class FinancialRecord
{
    public function __construct(
        public readonly string $id,
        public readonly ?\DateTimeImmutable $date,
        public readonly string $status,
        public readonly bool $included,
        public readonly ?bool $income,
        public readonly string $partyName,
        public readonly ?int $baseCents,
        public readonly string $tax,
        public readonly ?int $taxCents,
        public readonly int $surchargeCents,
        public readonly int $withholdingCents,
        public readonly ?int $totalCents,
        public readonly int $version,
    ) {
    }

    public function movement(): QuarterMovement
    {
        if (null === $this->date || null === $this->income || null === $this->baseCents || null === $this->taxCents || null === $this->totalCents) {
            throw new InvalidQuarter('An included record has incomplete financial data.');
        }

        return new QuarterMovement($this->id, $this->date, $this->income, $this->partyName, $this->baseCents, $this->tax, $this->taxCents, $this->surchargeCents, $this->withholdingCents, $this->totalCents);
    }
}
