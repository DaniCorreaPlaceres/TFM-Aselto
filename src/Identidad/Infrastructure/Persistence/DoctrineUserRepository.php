<?php

namespace App\Identidad\Infrastructure\Persistence;

use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineUserRepository implements UserRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function find(string $id): ?User
    {
        $record = $this->entityManager->find(DoctrineUser::class, $id);

        return $record instanceof DoctrineUser ? $record->toDomain() : null;
    }

    public function findByEmail(string $email): ?User
    {
        $record = $this->entityManager->getRepository(DoctrineUser::class)->findOneBy([
            'email' => mb_strtolower(trim($email)),
        ]);

        if (!$record instanceof DoctrineUser) {
            return null;
        }

        return $record->toDomain();
    }

    public function findByFirm(string $firmId): array
    {
        $users = [];

        foreach ($this->entityManager->getRepository(DoctrineUser::class)->findBy(['firmId' => $firmId]) as $record) {
            $users[] = $record->toDomain();
        }

        return $users;
    }

    public function save(User $user): void
    {
        $record = $this->entityManager->find(DoctrineUser::class, $user->id());

        if (!$record instanceof DoctrineUser) {
            $record = new DoctrineUser();
        }

        $record->apply($user);
        $this->entityManager->persist($record);
        $this->entityManager->flush();
    }
}
