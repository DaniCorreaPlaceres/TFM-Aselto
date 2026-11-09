<?php

namespace App\Finanzas\Domain;

interface FinancialRecords
{
    /** @return list<FinancialRecord> */
    public function forClient(string $ownerId, ?string $firmId): array;
}
