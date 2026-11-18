<?php

namespace App\Finanzas\Infrastructure\Realtime;

use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentRepository;
use App\Documento\Infrastructure\Persistence\DoctrineDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator(decorates: DoctrineDocumentRepository::class)]
final class NotifyingDocumentRepository implements DocumentRepository
{
    public function __construct(#[AutowireDecorated] private readonly DocumentRepository $inner, private readonly QuarterUpdateOutbox $outbox, private readonly EntityManagerInterface $entityManager)
    {
    }

    public function find(string $id): ?Document
    {
        return $this->inner->find($id);
    }

    public function findByOwner(string $clientUserId): array
    {
        return $this->inner->findByOwner($clientUserId);
    }

    public function findByFirm(string $firmId): array
    {
        return $this->inner->findByFirm($firmId);
    }

    public function save(Document $document): void
    {
        $this->entityManager->getConnection()->transactional(function () use ($document): void {
            $this->inner->save($document);
            $this->outbox->record($document->clientUserId(), $document->firmId());
        });
    }
}
