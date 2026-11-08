<?php

namespace App\Documento\Application;

use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentRepository;
use App\Shared\Domain\Transaction;

final class ConfirmDocument
{
    public function __construct(
        private readonly OpenClientDocument $documents,
        private readonly DocumentRepository $repository,
        private readonly Transaction $transaction,
    ) {
    }

    public function execute(
        string $userId,
        string $documentId,
        string $direction,
        string $issuedOn,
        string $partyName,
        string $taxId,
        string $base,
        string $taxRate,
        string $surcharge = '',
    ): Document {
        $document = $this->documents->execute($userId, $documentId);
        $document = $document->confirm(DocumentForm::read(
            $direction,
            $issuedOn,
            $partyName,
            $taxId,
            $base,
            $taxRate,
            $document->tax(),
            $surcharge,
        ));

        $this->transaction->run(function () use ($document): void {
            $this->repository->save($document);
        });

        return $document;
    }
}
