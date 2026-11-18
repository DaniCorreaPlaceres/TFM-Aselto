<?php

namespace App\Documento\Domain;

final class WithholdingRate
{
    private function __construct(private readonly int $basisPoints)
    {
        if ($basisPoints < 0 || $basisPoints > 10000) {
            throw new InvalidWithholding('The withholding percentage must be between zero and one hundred.');
        }
    }

    public static function fromBasisPoints(int $basisPoints): self
    {
        return new self($basisPoints);
    }

    public static function fromPercentage(string $percentage): self
    {
        $percentage = str_replace(',', '.', trim($percentage));

        if (1 !== preg_match('/^\d{1,3}(\.\d{1,2})?$/', $percentage)) {
            throw new InvalidWithholding('The withholding percentage is not valid.');
        }

        [$whole, $fraction] = array_pad(explode('.', $percentage, 2), 2, '0');

        return new self((int) $whole * 100 + (int) str_pad($fraction, 2, '0'));
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
