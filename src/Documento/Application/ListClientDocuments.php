<?php

namespace App\Documento\Application;

use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentRepository;

final class ListClientDocuments
{
    public function __construct(private readonly DocumentRepository $documents)
    {
    }

    /**
     * @return list<Document>
     */
    public function execute(string $userId): array
    {
        return $this->documents->findByOwner($userId);
    }

    /**
     * @return list<Document>
     */
    public function pending(string $userId): array
    {
        return array_values(array_filter(
            $this->documents->findByOwner($userId),
            static fn (Document $document): bool => $document->needsReview(),
        ));
    }
}
