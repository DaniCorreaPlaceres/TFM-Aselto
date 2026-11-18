<?php

namespace App\Finanzas\Infrastructure\Persistence;

use App\Finanzas\Domain\QuarterPeriod;
use App\Finanzas\Domain\QuarterRecordCatalog;
use App\Shared\Domain\RecordCriteria;
use App\Shared\Domain\RecordPage;
use Doctrine\DBAL\Connection;

final readonly class DoctrineQuarterRecordCatalog implements QuarterRecordCatalog
{
    public function __construct(private Connection $connection)
    {
    }

    public function hasScope(string $ownerId, ?string $firmId): bool
    {
        return false !== $this->connection->fetchOne('SELECT 1 FROM financial_quarter WHERE owner_id=? AND scope=? LIMIT 1', [$ownerId, $firmId ?? 'personal']);
    }

    public function page(string $ownerId, ?string $firmId, QuarterPeriod $period, RecordCriteria $criteria): RecordPage
    {
        $parameters = ['owner' => $ownerId, 'scope' => $firmId ?? 'personal', 'year' => $period->year, 'number' => $period->number];
        $closed = $this->connection->fetchOne('SELECT closed FROM financial_quarter WHERE owner_id=:owner AND scope=:scope AND year=:year AND number=:number', $parameters);
        if ($closed) {
            $from = "financial_quarter fq JOIN JSON_TABLE(fq.snapshot, '$[*]' COLUMNS(id VARCHAR(36) PATH '$.id', issued_on DATE PATH '$.date', income BOOLEAN PATH '$.income', party_name VARCHAR(180) PATH '$.partyName', base_cents BIGINT PATH '$.baseCents', tax VARCHAR(4) PATH '$.tax', tax_exempt BOOLEAN PATH '$.taxExempt' DEFAULT 'false' ON EMPTY, quota_cents BIGINT PATH '$.taxCents', total_cents BIGINT PATH '$.totalCents')) d";
            $where = 'fq.owner_id=:owner AND fq.scope=:scope AND fq.year=:year AND fq.number=:number';
            $direction = "CASE d.income WHEN 1 THEN 'Ingreso' ELSE 'Gasto' END";
            $name = 'd.party_name';
        } else {
            $from = 'client_document d';
            $where = 'd.client_user_id=:owner AND '.(null === $firmId ? 'd.firm_id IS NULL' : 'd.firm_id=:firm').' AND d.status=:status AND d.pending_request IS NULL AND d.issued_on >= :starts AND d.issued_on < :ends';
            $parameters = ['owner' => $ownerId, 'status' => null === $firmId ? 'submitted' : 'validated', 'starts' => $period->startsOn(), 'ends' => $period->endsBefore()];
            if (null !== $firmId) {
                $parameters['firm'] = $firmId;
            }
            $direction = "CASE d.direction WHEN 'income' THEN 'Ingreso' ELSE 'Gasto' END";
            $name = "COALESCE(NULLIF(d.party_name, ''), d.original_name)";
        }
        $tax = "CONCAT(UPPER(d.tax), CASE WHEN d.tax_exempt THEN ' · Exento' ELSE '' END, ': ', FORMAT(d.quota_cents/100, 2, 'de_DE'), ' €')";
        $columns = ['d.issued_on', $direction, $name, 'd.base_cents', $tax, 'd.total_cents'];
        $total = $criteria->count ? (int) $this->connection->fetchOne('SELECT /*+ MAX_EXECUTION_TIME(5000) */ COUNT(*) FROM '.$from.' WHERE '.$where, $parameters) : 0;
        if ('' !== $criteria->search) {
            $conditions = [];
            foreach ($columns as $index => $column) {
                if (in_array($index, [3, 5], true)) {
                    $column = "CONCAT(FORMAT($column/100, 2, 'de_DE'), ' €')";
                }
                if (0 === $index) {
                    $column = "CONCAT(d.issued_on, ' ', DATE_FORMAT(d.issued_on, '%d/%m/%Y'))";
                }
                $conditions[] = "LOWER(CAST($column AS CHAR)) LIKE :q$index ESCAPE '!'";
                $parameters['q'.$index] = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($criteria->search)).'%';
            }
            $where .= ' AND ('.implode(' OR ', $conditions).')';
        }
        if ('' !== $criteria->filter) {
            $where .= ' AND '.$direction.'=:filter';
            $parameters['filter'] = $criteria->filter;
        }
        $filtered = $criteria->count ? (int) $this->connection->fetchOne('SELECT /*+ MAX_EXECUTION_TIME(5000) */ COUNT(*) FROM '.$from.' WHERE '.$where, $parameters) : 0;
        $rows = $this->connection->fetchAllAssociative('SELECT /*+ MAX_EXECUTION_TIME(5000) */ d.id, d.issued_on, '.$direction.' AS direction, '.$name.' AS name, d.base_cents, '.$tax.' AS tax, d.total_cents FROM '.$from.' WHERE '.$where.' ORDER BY '.($columns[$criteria->column] ?? $columns[0]).' '.strtoupper($criteria->order).', d.id ASC LIMIT '.$criteria->limit.' OFFSET '.$criteria->offset, $parameters);

        return new RecordPage($rows, $total, $filtered);
    }
}
