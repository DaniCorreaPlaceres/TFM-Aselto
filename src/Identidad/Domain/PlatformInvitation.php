<?php

namespace App\Identidad\Domain;

final class PlatformInvitation
{
    public readonly string $email;

    public function __construct(
        public readonly string $id,
        string $email,
        public readonly InvitationTarget $target,
        public readonly string $tokenHash,
        public readonly \DateTimeImmutable $sentAt,
        public readonly \DateTimeImmutable $expiresAt,
        public readonly ?\DateTimeImmutable $acceptedAt = null,
    ) {
        $this->email = mb_strtolower(trim($email));
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL) || mb_strlen($this->email) > 180) {
            throw new InvalidUser('The email is not valid.');
        }
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id)
            || 1 !== preg_match('/^[a-f0-9]{64}$/', $tokenHash)) {
            throw new InvitationUnavailable('Invalid invitation.');
        }
    }

    public function isUsable(\DateTimeImmutable $now): bool
    {
        return null === $this->acceptedAt && $this->expiresAt > $now;
    }

    public function resend(string $tokenHash, \DateTimeImmutable $now): self
    {
        if (null !== $this->acceptedAt) {
            throw new InvitationUnavailable('Invitation already accepted.');
        }

        return new self($this->id, $this->email, $this->target, $tokenHash, $now, $now->modify('+24 hours'));
    }

    public function accept(\DateTimeImmutable $now): self
    {
        if (!$this->isUsable($now)) {
            throw new InvitationUnavailable('Invitation no longer usable.');
        }

        return new self($this->id, $this->email, $this->target, $this->tokenHash, $this->sentAt, $this->expiresAt, $now);
    }
}
