<?php

namespace App\Identidad\Domain;

final readonly class AdminEmailChange
{
    public function __construct(public string $userId, public string $sourceEmail, public string $newEmail, public string $tokenHash, public int $expiresAt, public int $authVersion, public ?int $confirmedAt = null, public bool $noticeSent = false)
    {
    }

    public function usable(User $user, int $now): bool
    {
        return null === $this->confirmedAt && $this->expiresAt > $now && $user->id() === $this->userId && $user->email() === $this->sourceEmail && $user->authVersion() === $this->authVersion;
    }

    public function confirm(int $now): self
    {
        return new self($this->userId, $this->sourceEmail, $this->newEmail, $this->tokenHash, $this->expiresAt, $this->authVersion, $now);
    }
}
