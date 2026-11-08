<?php

namespace App\Documento\Domain;

final class TaxRate
{
    private function __construct(private readonly int $basisPoints)
    {
        if ($basisPoints < 0 || $basisPoints > 10000) {
            throw new InvalidTaxRate('The tax rate is out of range.');
        }
    }

    public static function fromBasisPoints(Tax $tax, int $basisPoints): self
    {
        $rate = new self($basisPoints);

        if (!$tax->accepts($rate)) {
            throw new InvalidTaxRate('The rate does not belong to the document tax.');
        }

        return $rate;
    }

    public static function fromPercentage(Tax $tax, string $percentage): self
    {
        $percentage = str_replace(',', '.', trim($percentage));

        if (1 !== preg_match('/^\d{1,3}(\.\d{1,2})?$/', $percentage)) {
            throw new InvalidTaxRate('The tax rate is not valid.');
        }

        [$whole, $fraction] = array_pad(explode('.', $percentage, 2), 2, '0');

        return self::fromBasisPoints($tax, (int) $whole * 100 + (int) str_pad($fraction, 2, '0'));
    }

    public function basisPoints(): int
    {
        return $this->basisPoints;
    }

    public function percentage(): string
    {
        return rtrim(rtrim(number_format($this->basisPoints / 100, 2, '.', ''), '0'), '.');
    }

    public function quotaFor(int $baseCents): int
    {
        return intdiv($baseCents * $this->basisPoints + 5000, 10000);
    }
}
