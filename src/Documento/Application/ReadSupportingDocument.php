<?php

namespace App\Documento\Application;

use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentNotFound;
use App\Documento\Domain\SupportingDocument;

final class ReadSupportingDocument
{
    public function __construct(private readonly OpenClientDocument $clients, private readonly FirmDocumentAccess $firms)
    {
    }

    public function forOwner(string $ownerId, string $documentId, string $fileId): SupportingDocument
    {
        return $this->find($this->clients->execute($ownerId, $documentId), $fileId);
    }

    public function forFirm(string $actorId, string $documentId, string $fileId): SupportingDocument
    {
        return $this->find($this->firms->open($actorId, $documentId)->document, $fileId);
    }

    private function find(Document $document, string $fileId): SupportingDocument
    {
        foreach ($document->supportingFiles() as $file) {
            if ($file->id === $fileId) {
                return $file;
            }
        }
        throw new DocumentNotFound('The supporting document does not exist.');
    }
}
