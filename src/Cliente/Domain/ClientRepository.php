<?php

namespace App\Cliente\Domain;

interface ClientRepository
{
    public function find(string $id): ?Client;

    public function findByUserId(string $userId): ?Client;

    /**
     * @return list<Client>
     */
    public function findByFirm(string $firmId): array;

    /**
     * @return list<Client>
     */
    public function findWithoutFirm(): array;

    public function save(Client $client): void;
}
