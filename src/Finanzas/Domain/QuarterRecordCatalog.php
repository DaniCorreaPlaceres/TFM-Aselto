<?php

namespace App\Finanzas\Domain;

use App\Shared\Domain\RecordCriteria;
use App\Shared\Domain\RecordPage;

interface QuarterRecordCatalog
{
    public function hasScope(string $ownerId, ?string $firmId): bool;

    public function page(string $ownerId, ?string $firmId, QuarterPeriod $period, RecordCriteria $criteria): RecordPage;
}
