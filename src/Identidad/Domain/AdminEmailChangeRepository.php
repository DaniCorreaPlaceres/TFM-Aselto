<?php

namespace App\Identidad\Domain;

interface AdminEmailChangeRepository
{
    public function find(string $userId): ?AdminEmailChange;

    public function findByHash(string $hash): ?AdminEmailChange;

    public function save(AdminEmailChange $change): void;

    public function markNotified(string $hash): void;

    public function delete(string $userId): void;
}
