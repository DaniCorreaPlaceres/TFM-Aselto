<?php

namespace App\Identidad\Infrastructure\Persistence;

use App\Identidad\Domain\AdminEmailChange;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'admin_email_change')]
#[ORM\UniqueConstraint(name: 'uniq_admin_email_change_token', columns: ['token_hash'])]
class DoctrineAdminEmailChange
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $userId;

    #[ORM\Column(length: 180)]
    private string $sourceEmail;

    #[ORM\Column(length: 180)]
    private string $newEmail;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\Column(type: 'bigint')]
    private int $expiresAt;

    #[ORM\Column]
    private int $authVersion;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $confirmedAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private bool $noticeSent = false;

    public function apply(AdminEmailChange $change): void
    {
        $this->userId = $change->userId;
        $this->sourceEmail = $change->sourceEmail;
        $this->newEmail = $change->newEmail;
        $this->tokenHash = $change->tokenHash;
        $this->expiresAt = $change->expiresAt;
        $this->authVersion = $change->authVersion;
        $this->confirmedAt = $change->confirmedAt;
        $this->noticeSent = $change->noticeSent;
    }

    public function toDomain(): AdminEmailChange
    {
        return new AdminEmailChange($this->userId, $this->sourceEmail, $this->newEmail, $this->tokenHash, $this->expiresAt, $this->authVersion, $this->confirmedAt, $this->noticeSent);
    }
}
