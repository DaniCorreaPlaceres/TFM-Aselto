<?php

namespace App\Documento\Application;

use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentFileStore;
use App\Documento\Domain\DocumentRepository;
use App\Documento\Domain\DocumentStatus;
use App\Documento\Domain\InvalidDocument;
use App\Documento\Domain\SupportingDocument;
use App\Shared\Domain\Clock;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\Transaction;

final class SupplySupportingDocument
{
    public function __construct(private readonly OpenClientDocument $access, private readonly DocumentRepository $documents, private readonly DocumentFileStore $files, private readonly IdGenerator $ids, private readonly Clock $clock, private readonly Transaction $transaction)
    {
    }

    public function execute(string $ownerId, string $documentId, int $version, string $originalName, string $mediaType, string $contents): Document
    {
        $document = $this->access->execute($ownerId, $documentId);
        $document->assertVersion($version);
        if (DocumentStatus::Submitted !== $document->status() || null === $document->pendingRequest()) {
            throw new InvalidDocument('No supporting document is currently requested.');
        }
        $upload = DocumentUpload::read($originalName, $mediaType, $contents);
        $id = $this->ids->next();
        $path = $this->files->write($id, $upload['extension'], $contents);
        $at = $this->clock->now();
        $updated = $document->attachSupportingDocument(new SupportingDocument($id, $upload['originalName'], $path, $upload['mediaType'], $at), $ownerId, $at);
        $this->transaction->run(function () use ($updated): void { $this->documents->save($updated); });

        return $updated;
    }
}
