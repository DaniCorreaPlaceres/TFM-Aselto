<?php

namespace App\Documento\Application;

use App\Cliente\Domain\ClientRepository;
use App\Documento\Domain\DocumentNotFound;
use App\Documento\Domain\DocumentRepository;
use App\Documento\Domain\DocumentStatus;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\UserRepository;

final class FirmDocumentAccess
{
    public function __construct(private readonly UserRepository $users, private readonly ClientRepository $clients, private readonly DocumentRepository $documents)
    {
    }

    public function firmFor(string $actorId): string
    {
        $actor = $this->users->find($actorId);
        if (null === $actor || !$actor->isActivated() || !in_array($actor->role(), [Role::FirmAdmin, Role::Advisor], true) || null === $actor->firmId()) {
            throw new DocumentNotFound('The firm document does not exist.');
        }

        return $actor->firmId();
    }

    public function open(string $actorId, string $documentId): FirmDocument
    {
        $firmId = $this->firmFor($actorId);
        $document = $this->documents->find($documentId);
        if (null === $document || $document->firmId() !== $firmId || !in_array($document->status(), [DocumentStatus::Submitted, DocumentStatus::Rejected, DocumentStatus::Validated], true)) {
            throw new DocumentNotFound('The firm document does not exist.');
        }
        $client = $this->clients->findByUserId($document->clientUserId());
        if (null === $client || $client->firmId() !== $firmId) {
            throw new DocumentNotFound('The firm document does not exist.');
        }

        return new FirmDocument($document, $client->name());
    }
}
