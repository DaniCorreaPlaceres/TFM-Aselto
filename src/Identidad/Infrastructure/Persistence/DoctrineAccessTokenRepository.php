<?php

namespace App\Identidad\Infrastructure\Persistence;

use App\Identidad\Domain\AccessPurpose;
use App\Identidad\Domain\AccessToken;
use App\Identidad\Domain\AccessTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineAccessTokenRepository implements AccessTokenRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findByHash(string $tokenHash): ?AccessToken
    {
        $record = $this->entityManager->getRepository(DoctrineAccessToken::class)->findOneBy([
            'tokenHash' => $tokenHash,
        ]);

        if ($record instanceof DoctrineAccessToken) {
            $this->entityManager->refresh($record);

            return $record->toDomain();
        }

        return null;
    }

    public function discardOpen(string $userId, AccessPurpose $purpose): void
    {
        $records = $this->entityManager->getRepository(DoctrineAccessToken::class)->findBy([
            'userId' => $userId,
            'purpose' => $purpose->value,
            'usedAt' => null,
        ]);

        foreach ($records as $record) {
            $this->entityManager->remove($record);
        }

        $this->entityManager->flush();
    }

    public function save(AccessToken $token): void
    {
        $record = $this->entityManager->find(DoctrineAccessToken::class, $token->id());

        if (!$record instanceof DoctrineAccessToken) {
            $record = new DoctrineAccessToken();
        }

        $record->apply($token);
        $this->entityManager->persist($record);
        $this->entityManager->flush();
    }
}
