<?php

namespace App\Documento\Domain;

enum VatRate: int
{
    case Zero = 0;
    case SuperReduced = 4;
    case Reduced = 10;
    case General = 21;

    public function quotaFor(int $baseCents): int
    {
        return (int) round($baseCents * $this->value / 100);
    }
}
