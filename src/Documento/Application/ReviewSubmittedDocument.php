<?php

namespace App\Documento\Application;

use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentDetails;
use App\Documento\Domain\DocumentRepository;
use App\Documento\Domain\InvalidDocument;
use App\Documento\Domain\ReviewAction;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Transaction;

final class ReviewSubmittedDocument
{
    public function __construct(private readonly FirmDocumentAccess $access, private readonly DocumentRepository $documents, private readonly DocumentWithholdingPolicy $withholdingPolicy, private readonly Clock $clock, private readonly Transaction $transaction)
    {
    }

    public function execute(string $actorId, string $documentId, int $version, ReviewAction $action, ?DocumentDetails $details = null, string $reason = ''): Document
    {
        $document = $this->access->open($actorId, $documentId)->document;
        $document->assertVersion($version);
        if (ReviewAction::Validated === $action) {
            if (null === $details) {
                throw new InvalidDocument('The reviewed details are missing.');
            }
            $this->withholdingPolicy->assertAllowed($document->clientUserId(), $details->withholdingRate());
        }
        $at = $this->clock->now();
        $reviewed = match ($action) {
            ReviewAction::Validated => $document->validate($details, $actorId, $at),
            ReviewAction::Rejected => $document->reject($reason, $actorId, $at),
            ReviewAction::Requested => $document->requestSupportingDocument($reason, $actorId, $at),
            default => throw new InvalidDocument('The review action is not allowed.'),
        };
        $this->transaction->run(function () use ($reviewed): void { $this->documents->save($reviewed); });

        return $reviewed;
    }
}
