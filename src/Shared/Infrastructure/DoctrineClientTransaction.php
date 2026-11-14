<?php

namespace App\Shared\Infrastructure;

use App\Cliente\Domain\UnknownClient;
use App\Shared\Domain\ClientTransaction;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineClientTransaction implements ClientTransaction
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function run(string $ownerId, callable $operation): mixed
    {
        $connection = $this->entityManager->getConnection();

        return $connection->transactional(function () use ($connection, $ownerId, $operation): mixed {
            if (false === $connection->fetchOne('SELECT id FROM client_profile WHERE user_id = ? FOR UPDATE', [$ownerId])) {
                throw new UnknownClient('The client profile no longer exists.');
            }
            $result = $operation();
            $this->entityManager->flush();

            return $result;
        });
    }
}
