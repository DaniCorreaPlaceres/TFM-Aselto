<?php

namespace App\Finanzas\Infrastructure\Persistence;

use App\Finanzas\Domain\FinancialRecord;
use App\Finanzas\Domain\FinancialRecords;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineFinancialRecords implements FinancialRecords
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function forClient(string $ownerId, ?string $firmId): array
    {
        $where = null === $firmId ? 'firm_id IS NULL' : 'firm_id = :firm';
        $parameters = ['owner' => $ownerId];
        if (null !== $firmId) {
            $parameters['firm'] = $firmId;
        }
        $rows = $this->entityManager->getConnection()->fetchAllAssociative('SELECT id, issued_on, status, direction, party_name, original_name, base_cents, tax, quota_cents, surcharge_quota_cents, withholding_quota_cents, total_cents, pending_request, version FROM client_document WHERE client_user_id = :owner AND '.$where.' ORDER BY id', $parameters);

        return array_map(static fn (array $row): FinancialRecord => new FinancialRecord(
            (string) $row['id'],
            null === $row['issued_on'] ? null : new \DateTimeImmutable((string) $row['issued_on']),
            (string) $row['status'],
            $row['status'] === (null === $firmId ? 'submitted' : 'validated') && null === $row['pending_request'],
            in_array($row['direction'], ['income', 'expense'], true) ? 'income' === $row['direction'] : null,
            (string) ($row['party_name'] ?: $row['original_name']),
            null === $row['base_cents'] ? null : (int) $row['base_cents'],
            (string) $row['tax'],
            null === $row['quota_cents'] ? null : (int) $row['quota_cents'],
            (int) ($row['surcharge_quota_cents'] ?? 0),
            (int) ($row['withholding_quota_cents'] ?? 0),
            null === $row['total_cents'] ? null : (int) $row['total_cents'],
            (int) $row['version'],
        ), $rows);
    }
}
