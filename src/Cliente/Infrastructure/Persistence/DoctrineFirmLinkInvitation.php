<?php

namespace App\Cliente\Infrastructure\Persistence;

use App\Cliente\Domain\FirmLinkInvitation;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'firm_link_invitation')]
#[ORM\UniqueConstraint(name: 'uniq_firm_link_pair', columns: ['owner_id', 'firm_id'])]
#[ORM\UniqueConstraint(name: 'uniq_firm_link_token', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_firm_link_firm', columns: ['firm_id'])]
class DoctrineFirmLinkInvitation
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $id;
    #[ORM\Column(length: 36)]
    private string $ownerId;
    #[ORM\Column(length: 36)]
    private string $firmId;
    #[ORM\Column(length: 180)]
    private string $email;
    #[ORM\Column(length: 64)]
    private string $tokenHash;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $sentAt;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;
    #[ORM\Column(length: 36)]
    private string $sentBy;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $acceptedAt;

    public function apply(FirmLinkInvitation $invitation): void
    {
        $this->id = $invitation->id;
        $this->ownerId = $invitation->ownerId;
        $this->firmId = $invitation->firmId;
        $this->email = $invitation->email;
        $this->tokenHash = $invitation->tokenHash;
        $this->sentBy = $invitation->sentBy;
        $utc = new \DateTimeZone('UTC');
        $this->sentAt = $invitation->sentAt->setTimezone($utc);
        $this->expiresAt = $invitation->expiresAt->setTimezone($utc);
        $this->acceptedAt = $invitation->acceptedAt?->setTimezone($utc);
    }

    public function toDomain(): FirmLinkInvitation
    {
        $utc = static fn (\DateTimeImmutable $at): \DateTimeImmutable => new \DateTimeImmutable($at->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));

        return new FirmLinkInvitation($this->id, $this->ownerId, $this->firmId, $this->email, $this->tokenHash, $utc($this->sentAt), $utc($this->expiresAt), $this->sentBy, null === $this->acceptedAt ? null : $utc($this->acceptedAt));
    }
}
