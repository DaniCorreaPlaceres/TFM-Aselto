<?php

namespace App\Finanzas\Infrastructure\Realtime;

use Doctrine\ORM\EntityManagerInterface;

final class QuarterUpdateOutbox
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function record(string $ownerId, ?string $firmId): void
    {
        $connection = $this->entityManager->getConnection();
        if (!$connection->isTransactionActive()) {
            throw new \LogicException('Financial notifications must be recorded in the business transaction.');
        }
        $connection->executeStatement('INSERT INTO quarter_update_outbox (owner_id, scope, revision, published_revision) VALUES (?, ?, 1, 0) ON DUPLICATE KEY UPDATE revision = revision + 1', [$ownerId, $firmId ?? 'personal']);
    }
}
