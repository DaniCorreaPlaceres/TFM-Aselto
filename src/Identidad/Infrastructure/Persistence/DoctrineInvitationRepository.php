<?php

namespace App\Identidad\Infrastructure\Persistence;

use App\Identidad\Domain\EmailAlreadyUsed;
use App\Identidad\Domain\InvitationAlreadyExists;
use App\Identidad\Domain\InvitationRepository;
use App\Identidad\Domain\InvitationUnavailable;
use App\Identidad\Domain\PlatformInvitation;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineInvitationRepository implements InvitationRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function all(): array
    {
        return array_map(static fn (DoctrineInvitation $record): PlatformInvitation => $record->toDomain(), $this->entityManager->getRepository(DoctrineInvitation::class)->findBy([], ['sentAt' => 'DESC']));
    }

    public function findByHash(string $hash): ?PlatformInvitation
    {
        return $this->entityManager->getRepository(DoctrineInvitation::class)->findOneBy(['tokenHash' => $hash])?->toDomain();
    }

    public function save(PlatformInvitation $invitation): void
    {
        $record = $this->entityManager->find(DoctrineInvitation::class, $invitation->id) ?? new DoctrineInvitation();
        $record->apply($invitation);
        $this->entityManager->persist($record);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $error) {
            throw new InvitationAlreadyExists('Invitation already exists.', previous: $error);
        }
    }

    public function locked(string $id, callable $operation): void
    {
        try {
            $this->entityManager->wrapInTransaction(function () use ($id, $operation): void {
                $record = $this->entityManager->find(DoctrineInvitation::class, $id);
                if (null === $record) {
                    throw new InvitationUnavailable('Invitation not found.');
                }
                $this->entityManager->refresh($record, LockMode::PESSIMISTIC_WRITE);
                $operation($record->toDomain());
            });
        } catch (UniqueConstraintViolationException $error) {
            throw new EmailAlreadyUsed('The email already belongs to an account.', previous: $error);
        }
    }
}
