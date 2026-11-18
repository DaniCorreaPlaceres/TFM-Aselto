<?php

namespace App\Documento\Application;

use App\Documento\Domain\DocumentNotFound;
use App\Documento\Domain\DocumentRepository;

final class ListFirmDocuments
{
    public function __construct(private readonly FirmDocumentAccess $access, private readonly DocumentRepository $documents)
    {
    }

    /** @return list<FirmDocument> */
    public function execute(string $actorId): array
    {
        $firmId = $this->access->firmFor($actorId);
        $visible = [];
        foreach ($this->documents->findByFirm($firmId) as $document) {
            try {
                $visible[] = $this->access->open($actorId, $document->id());
            } catch (DocumentNotFound) {
            }
        }

        return $visible;
    }
}
