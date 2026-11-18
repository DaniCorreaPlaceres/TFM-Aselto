<?php

namespace App\Shared\Infrastructure;

use App\Shared\Domain\Transaction;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTransaction implements Transaction
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function run(callable $operation): void
    {
        $this->entityManager->wrapInTransaction($operation);
    }
}
