<?php

namespace App\Documento\Domain;

enum EquivalenceSurcharge: int
{
    case SuperReduced = 50;
    case Reduced = 140;
    case General = 520;
    case Tobacco = 175;

    public static function fromPercentage(string $percentage): self
    {
        try {
            $rate = TaxRate::fromPercentage(Tax::Ipsi, $percentage);
        } catch (InvalidTaxRate) {
            throw new InvalidSurcharge('The surcharge rate is not valid.');
        }

        return self::tryFrom($rate->basisPoints()) ?? throw new InvalidSurcharge('The surcharge rate is not supported.');
    }

    public function percentage(): string
    {
        return TaxRate::fromBasisPoints(Tax::Ipsi, $this->value)->percentage();
    }

    public function quotaFor(int $baseCents): int
    {
        return intdiv($baseCents * $this->value + 5000, 10000);
    }

    public function supports(?MovementDirection $direction, Tax $tax, ?TaxRate $rate): bool
    {
        return MovementDirection::Expense === $direction && Tax::Vat === $tax && $rate?->basisPoints() === match ($this) {
            self::General, self::Tobacco => 2100,
            self::Reduced => 1000,
            self::SuperReduced => 400,
        };
    }
}
