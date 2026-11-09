<?php

namespace App\Documento\Infrastructure\Persistence;

use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentChanged;
use App\Documento\Domain\DocumentRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

final class DoctrineDocumentRepository implements DocumentRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function find(string $id): ?Document
    {
        $record = $this->entityManager->find(DoctrineDocument::class, $id);

        return $record instanceof DoctrineDocument ? $record->toDomain() : null;
    }

    public function findByOwner(string $clientUserId): array
    {
        return $this->map($this->entityManager->getRepository(DoctrineDocument::class)->findBy(
            ['clientUserId' => $clientUserId],
            ['createdAt' => 'DESC'],
        ));
    }

    public function findByFirm(string $firmId): array
    {
        return $this->map($this->entityManager->getRepository(DoctrineDocument::class)->findBy(
            ['firmId' => $firmId, 'status' => ['submitted', 'validated', 'rejected']],
            ['createdAt' => 'ASC'],
        ));
    }

    public function save(Document $document): void
    {
        try {
            $record = $this->entityManager->find(DoctrineDocument::class, $document->id());

            if (!$record instanceof DoctrineDocument) {
                $record = new DoctrineDocument();
            } else {
                $this->entityManager->lock($record, LockMode::OPTIMISTIC, $document->version());
            }

            $record->apply($document);
            $this->entityManager->persist($record);
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            throw new DocumentChanged('The document was modified by another person.');
        }
    }

    /**
     * @param list<DoctrineDocument> $records
     *
     * @return list<Document>
     */
    private function map(array $records): array
    {
        $documents = [];

        foreach ($records as $record) {
            $documents[] = $record->toDomain();
        }

        return $documents;
    }
}
