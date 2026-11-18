<?php

namespace App\Identidad\Infrastructure\Persistence;

use App\Identidad\Domain\InvitationTarget;
use App\Identidad\Domain\PlatformInvitation;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'platform_invitation')]
#[ORM\UniqueConstraint(name: 'uniq_platform_invitation_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_platform_invitation_token', columns: ['token_hash'])]
class DoctrineInvitation
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $id;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 16, enumType: InvitationTarget::class)]
    private InvitationTarget $target;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $sentAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    public function apply(PlatformInvitation $invitation): void
    {
        $this->id = $invitation->id;
        $this->email = $invitation->email;
        $this->target = $invitation->target;
        $this->tokenHash = $invitation->tokenHash;
        $utc = new \DateTimeZone('UTC');
        $this->sentAt = $invitation->sentAt->setTimezone($utc);
        $this->expiresAt = $invitation->expiresAt->setTimezone($utc);
        $this->acceptedAt = $invitation->acceptedAt?->setTimezone($utc);
    }

    public function toDomain(): PlatformInvitation
    {
        // MySQL DATETIME has no timezone; these columns always store UTC wall time.
        // Doctrine hydrates using PHP's default timezone, which can differ from UTC.
        $utc = static fn (\DateTimeImmutable $date): \DateTimeImmutable => new \DateTimeImmutable($date->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));

        return new PlatformInvitation($this->id, $this->email, $this->target, $this->tokenHash, $utc($this->sentAt), $utc($this->expiresAt), null === $this->acceptedAt ? null : $utc($this->acceptedAt));
    }
}
