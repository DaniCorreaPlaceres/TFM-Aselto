<?php

namespace App\Documento\Domain;

use App\Shared\Domain\SpanishProvince;

enum Tax: string
{
    case Vat = 'iva';
    case Igic = 'igic';
    case Ipsi = 'ipsi';

    public static function forProvince(string $province): self
    {
        return match (SpanishProvince::codeFor($province)) {
            null => throw new MissingFiscalProvince('The client province is missing.'),
            '35', '38' => self::Igic,
            '51', '52' => self::Ipsi,
            default => self::Vat,
        };
    }

    /** @return list<int> */
    public function allowedBasisPoints(): array
    {
        return match ($this) {
            self::Vat => [0, 400, 1000, 2100],
            self::Igic => [0, 300, 700, 950, 1500, 2000],
            self::Ipsi => [],
        };
    }

    public function accepts(TaxRate $rate): bool
    {
        return self::Ipsi === $this || in_array($rate->basisPoints(), $this->allowedBasisPoints(), true);
    }
}
