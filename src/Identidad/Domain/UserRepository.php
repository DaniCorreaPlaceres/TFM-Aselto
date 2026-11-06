<?php

namespace App\Identidad\Domain;

interface UserRepository
{
    public function findByEmail(string $email): ?User;

    public function save(User $user): void;
}
