<?php

namespace App\Documento\Application;

use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentRepository;
use App\Shared\Domain\ClientTransaction;
use App\Shared\Domain\ClosedPeriodGuard;

final class SaveDocumentDraft
{
    public function __construct(
        private readonly OpenClientDocument $documents,
        private readonly DocumentRepository $repository,
        private readonly ClientTransaction $transaction,
        private readonly DocumentWithholdingPolicy $withholdingPolicy,
        private readonly ClosedPeriodGuard $closedPeriods,
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
        string $withholdingRate = '',
        ?int $expectedVersion = null,
    ): Document {
        return $this->transaction->run($userId, function () use ($userId, $documentId, $direction, $issuedOn, $partyName, $taxId, $base, $taxRate, $surcharge, $withholdingRate, $expectedVersion): Document {
            $document = $this->documents->execute($userId, $documentId);
            if (null !== $expectedVersion) {
                $document->assertVersion($expectedVersion);
            }
            $originalDate = $document->issuedOn();
            $document = $document->saveDraft(DocumentForm::read(
                $direction,
                $issuedOn,
                $partyName,
                $taxId,
                $base,
                $taxRate,
                $document->tax(),
                $surcharge,
                $withholdingRate,
            ));

            $this->withholdingPolicy->assertAllowed($userId, $document->withholdingRate());

            $this->closedPeriods->assertOpen($userId, $document->firmId(), $originalDate, $document->issuedOn());
            $this->repository->save($document);

            return $document;
        });
    }
}
