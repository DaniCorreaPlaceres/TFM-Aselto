<?php

namespace App\Finanzas\Application;

use App\Finanzas\Domain\QuarterNotFound;
use App\Finanzas\Domain\QuarterPeriod;
use App\Finanzas\Domain\QuarterRecordCatalog;
use App\Shared\Domain\Clock;
use App\Shared\Domain\RecordCriteria;
use App\Shared\Domain\RecordPage;

final readonly class BrowseQuarterRecords
{
    public function __construct(private QuarterAccess $access, private QuarterRecordCatalog $catalog, private Clock $clock)
    {
    }

    public function currentPeriod(): QuarterPeriod
    {
        return QuarterPeriod::forDate($this->clock->now()->setTimezone(new \DateTimeZone('Atlantic/Canary')));
    }

    public function execute(string $actorId, ?string $clientId, QuarterPeriod $period, ?string $scope, RecordCriteria $criteria): RecordPage
    {
        $access = $this->access->resolve($actorId, $clientId);
        $firmId = null === $scope ? $access->firmId : ('personal' === $scope ? null : $scope);
        if ($firmId !== $access->firmId && ((!$access->owner && null !== $firmId) || !$this->catalog->hasScope($access->ownerId, $firmId))) {
            throw new QuarterNotFound('The quarter scope is not available.');
        }
        $page = $this->catalog->page($access->ownerId, $firmId, $period, $criteria);

        return new RecordPage(array_map(static fn (array $row): array => [...$row, 'owner_view' => $access->owner, 'firm_view' => null !== $firmId], $page->rows), $page->total, $page->filtered);
    }
}
