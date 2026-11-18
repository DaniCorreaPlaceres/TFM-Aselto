<?php

namespace App\Documento\Application;

use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentNotFound;
use App\Documento\Domain\DocumentRepository;

final class OpenClientDocument
{
    public function __construct(private readonly DocumentRepository $documents)
    {
    }

    public function execute(string $userId, string $documentId): Document
    {
        $document = $this->documents->find($documentId);

        if (null === $document || $document->clientUserId() !== $userId) {
            throw new DocumentNotFound('The document does not exist.');
        }

        return $document;
    }
}
