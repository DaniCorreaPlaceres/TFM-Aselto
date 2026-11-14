<?php

namespace App\Identidad\Infrastructure\Persistence;

use App\Identidad\Domain\AdminEmailChange;
use App\Identidad\Domain\AdminEmailChangeRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineAdminEmailChangeRepository implements AdminEmailChangeRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function find(string $userId): ?AdminEmailChange
    {
        $record = $this->em->find(DoctrineAdminEmailChange::class, $userId);
        if (null !== $record) {
            $this->em->refresh($record);
        }

        return $record?->toDomain();
    }

    public function findByHash(string $hash): ?AdminEmailChange
    {
        return $this->em->getRepository(DoctrineAdminEmailChange::class)->findOneBy(['tokenHash' => $hash])?->toDomain();
    }

    public function save(AdminEmailChange $change): void
    {
        $record = $this->em->find(DoctrineAdminEmailChange::class, $change->userId) ?? new DoctrineAdminEmailChange();
        $record->apply($change);
        $this->em->persist($record);
        $this->em->flush();
    }

    public function markNotified(string $hash): void
    {
        $this->em->getConnection()->executeStatement('UPDATE admin_email_change SET notice_sent = 1 WHERE token_hash = ? AND confirmed_at IS NOT NULL', [$hash]);
    }

    public function delete(string $userId): void
    {
        $record = $this->em->find(DoctrineAdminEmailChange::class, $userId);
        if (null !== $record) {
            $this->em->remove($record);
            $this->em->flush();
        }
    }
}
