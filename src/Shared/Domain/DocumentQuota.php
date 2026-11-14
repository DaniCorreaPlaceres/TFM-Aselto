<?php

namespace App\Shared\Domain;

interface DocumentQuota
{
    /** Consume inside the document creation transaction; rollback on failure. */
    public function consume(string $ownerId, string $documentId, \DateTimeImmutable $at): void;
}
