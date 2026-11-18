<?php

namespace App\Documento\Domain;

interface DocumentRepository
{
    public function find(string $id): ?Document;

    /**
     * @return list<Document>
     */
    public function findByOwner(string $clientUserId): array;

    /** @return list<Document> */
    public function findByFirm(string $firmId): array;

    public function save(Document $document): void;
}
