<?php

namespace App\Identidad\Domain;

interface AccessTokenRepository
{
    public function findByHash(string $tokenHash): ?AccessToken;

    public function discardOpen(string $userId, AccessPurpose $purpose): void;

    public function save(AccessToken $token): void;
}
