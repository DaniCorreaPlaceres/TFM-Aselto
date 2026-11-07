<?php

namespace App\Identidad\Infrastructure\Persistence;

use App\Identidad\Domain\AccessPurpose;
use App\Identidad\Domain\AccessToken;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'access_token')]
class DoctrineAccessToken
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $id;

    #[ORM\Column(length: 36)]
    private string $userId;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(length: 16)]
    private string $purpose;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    public function apply(AccessToken $token): void
    {
        $this->id = $token->id();
        $this->userId = $token->userId();
        $this->tokenHash = $token->tokenHash();
        $this->purpose = $token->purpose()->value;
        $this->expiresAt = $token->expiresAt();
        $this->usedAt = $token->usedAt();
    }

    public function toDomain(): AccessToken
    {
        return new AccessToken(
            $this->id,
            $this->userId,
            $this->tokenHash,
            AccessPurpose::from($this->purpose),
            $this->expiresAt,
            $this->usedAt,
        );
    }
}
