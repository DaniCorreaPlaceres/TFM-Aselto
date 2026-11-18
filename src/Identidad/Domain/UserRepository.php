<?php

namespace App\Identidad\Domain;

interface UserRepository
{
    public function find(string $id): ?User;

    public function findByEmail(string $email): ?User;

    /**
     * @return list<User>
     */
    public function findByFirm(string $firmId): array;

    public function save(User $user): void;
}
