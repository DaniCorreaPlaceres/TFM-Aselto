<?php

namespace App\Cliente\Domain;

use App\Identidad\Domain\InvitationUnavailable;

final readonly class FirmLinkInvitation
{
    public function __construct(
        public string $id,
        public string $ownerId,
        public string $firmId,
        public string $email,
        public string $tokenHash,
        public \DateTimeImmutable $sentAt,
        public \DateTimeImmutable $expiresAt,
        public string $sentBy,
        public ?\DateTimeImmutable $acceptedAt = null,
    ) {
    }

    public function usable(\DateTimeImmutable $now): bool
    {
        return null === $this->acceptedAt && $now < $this->expiresAt;
    }

    public function accept(\DateTimeImmutable $now): self
    {
        if (!$this->usable($now)) {
            throw new InvitationUnavailable('Invitation is no longer available.');
        }

        return new self($this->id, $this->ownerId, $this->firmId, $this->email, $this->tokenHash, $this->sentAt, $this->expiresAt, $this->sentBy, $now);
    }
}
