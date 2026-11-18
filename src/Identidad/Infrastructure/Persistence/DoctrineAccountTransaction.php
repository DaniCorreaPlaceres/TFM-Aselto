<?php

namespace App\Identidad\Infrastructure\Persistence;

use App\Identidad\Domain\AccountTransaction;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineAccountTransaction implements AccountTransaction
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function forUser(string $userId, callable $operation): void
    {
        $this->em->wrapInTransaction(function () use ($userId, $operation): void {
            $user = $this->em->find(DoctrineUser::class, $userId);
            if (null !== $user) {
                $this->em->refresh($user, LockMode::PESSIMISTIC_WRITE);
            }
            $operation();
        });
    }
}
