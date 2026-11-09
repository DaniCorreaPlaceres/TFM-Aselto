<?php

namespace App\Comunicacion\Infrastructure\Persistence;

use App\Comunicacion\Domain\Conversation;
use App\Comunicacion\Domain\ConversationRepository;
use App\Comunicacion\Domain\InvalidMessage;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

final class DoctrineConversationRepository implements ConversationRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function find(string $id): ?Conversation
    {
        $record = $this->entityManager->find(DoctrineConversation::class, $id);
        if (!$record instanceof DoctrineConversation) {
            return null;
        }
        $this->entityManager->refresh($record);

        return $record->toDomain();
    }

    public function forOwner(string $ownerId): array
    {
        return $this->matching(['ownerId' => $ownerId]);
    }

    public function forFirm(string $firmId): array
    {
        return $this->matching(['firmId' => $firmId]);
    }

    public function save(Conversation $conversation): void
    {
        try {
            $record = $this->entityManager->find(DoctrineConversation::class, $conversation->id);
            if (!$record instanceof DoctrineConversation) {
                $record = new DoctrineConversation();
            } else {
                $this->entityManager->lock($record, LockMode::OPTIMISTIC, $conversation->version);
            }
            $record->apply($conversation);
            $this->entityManager->persist($record);
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            throw new InvalidMessage('La conversación ha cambiado. Recarga la página antes de continuar.');
        }
    }

    /**
     * @param array<string, string> $criteria
     *
     * @return list<Conversation>
     */
    private function matching(array $criteria): array
    {
        return array_map(function (DoctrineConversation $record): Conversation {
            $this->entityManager->refresh($record);

            return $record->toDomain();
        }, $this->entityManager->getRepository(DoctrineConversation::class)->findBy($criteria));
    }
}
