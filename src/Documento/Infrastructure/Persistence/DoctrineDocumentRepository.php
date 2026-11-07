<?php

namespace App\Documento\Infrastructure\Persistence;

use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;

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

    public function save(Document $document): void
    {
        $record = $this->entityManager->find(DoctrineDocument::class, $document->id());

        if (!$record instanceof DoctrineDocument) {
            $record = new DoctrineDocument();
        }

        $record->apply($document);
        $this->entityManager->persist($record);
        $this->entityManager->flush();
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
