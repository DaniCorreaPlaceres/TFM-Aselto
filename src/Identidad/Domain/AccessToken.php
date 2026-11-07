<?php

namespace App\Identidad\Domain;

final class AccessToken
{
    public function __construct(
        private readonly string $id,
        private readonly string $userId,
        private readonly string $tokenHash,
        private readonly AccessPurpose $purpose,
        private readonly \DateTimeImmutable $expiresAt,
        private readonly ?\DateTimeImmutable $usedAt,
    ) {
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id)) {
            throw new InvalidAccessToken('The id is not a valid UUID.');
        }

        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $userId)) {
            throw new InvalidAccessToken('The user id is not a valid UUID.');
        }

        if (1 !== preg_match('/^[a-f0-9]{64}$/', $tokenHash)) {
            throw new InvalidAccessToken('The token hash is not valid.');
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function purpose(): AccessPurpose
    {
        return $this->purpose;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function usedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function isUsable(\DateTimeImmutable $now, AccessPurpose $purpose): bool
    {
        return $this->purpose === $purpose
            && null === $this->usedAt
            && $this->expiresAt > $now;
    }

    public function use(\DateTimeImmutable $at): self
    {
        return new self($this->id, $this->userId, $this->tokenHash, $this->purpose, $this->expiresAt, $at);
    }
}
