<?php

namespace App\Documento\Application;

use App\Cliente\Domain\ClientRepository;
use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentNotFound;
use App\Documento\Domain\DocumentRepository;
use App\Shared\Domain\ClientTransaction;
use App\Shared\Domain\Clock;
use App\Shared\Domain\ClosedPeriodGuard;

final class AmendPersonalDocument
{
    public function __construct(private readonly OpenClientDocument $access, private readonly ClientRepository $clients, private readonly DocumentRepository $documents, private readonly ClientTransaction $transaction, private readonly ClosedPeriodGuard $closedPeriods, private readonly Clock $clock)
    {
    }

    public function execute(string $ownerId, string $documentId, int $version): Document
    {
        return $this->transaction->run($ownerId, function () use ($ownerId, $documentId, $version): Document {
            $client = $this->clients->findByUserId($ownerId);
            if (null === $client || null !== $client->firmId()) {
                throw new DocumentNotFound('The personal record cannot be amended.');
            }
            $document = $this->access->execute($ownerId, $documentId);
            $document->assertVersion($version);
            $this->closedPeriods->assertOpen($ownerId, $document->firmId(), $document->issuedOn());
            $updated = $document->amendPersonal($ownerId, $this->clock->now());
            $this->documents->save($updated);

            return $updated;
        });
    }
}
