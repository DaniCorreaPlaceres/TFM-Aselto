<?php

namespace App\Cliente\Infrastructure\Persistence;

use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineClientRepository implements ClientRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function find(string $id): ?Client
    {
        $record = $this->entityManager->find(DoctrineClient::class, $id);

        if (!$record instanceof DoctrineClient) {
            return null;
        }
        $this->entityManager->refresh($record);

        return $record->toDomain();
    }

    public function findByUserId(string $userId): ?Client
    {
        $record = $this->entityManager->getRepository(DoctrineClient::class)->findOneBy([
            'userId' => $userId,
        ]);

        if (!$record instanceof DoctrineClient) {
            return null;
        }
        $this->entityManager->refresh($record);

        return $record->toDomain();
    }

    public function findByFirm(string $firmId): array
    {
        return $this->map($this->entityManager->getRepository(DoctrineClient::class)->findBy(
            ['firmId' => $firmId],
            ['name' => 'ASC'],
        ));
    }

    public function findWithoutFirm(): array
    {
        return $this->map($this->entityManager->getRepository(DoctrineClient::class)->findBy(
            ['firmId' => null],
            ['name' => 'ASC'],
        ));
    }

    public function save(Client $client): void
    {
        $record = $this->entityManager->find(DoctrineClient::class, $client->id());

        if (!$record instanceof DoctrineClient) {
            $record = new DoctrineClient();
        }

        $record->apply($client);
        $this->entityManager->persist($record);
        $this->entityManager->flush();
    }

    /**
     * @param list<DoctrineClient> $records
     *
     * @return list<Client>
     */
    private function map(array $records): array
    {
        $clients = [];

        foreach ($records as $record) {
            $clients[] = $record->toDomain();
        }

        return $clients;
    }
}
