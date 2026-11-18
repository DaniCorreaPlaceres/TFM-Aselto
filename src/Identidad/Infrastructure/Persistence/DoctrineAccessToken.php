<?php

namespace App\Identidad\Infrastructure\Persistence;

use App\Identidad\Domain\AccessPurpose;
use App\Identidad\Domain\AccessToken;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'access_token')]
#[ORM\UniqueConstraint(name: 'uniq_access_token_hash', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_access_token_user', columns: ['user_id'])]
class DoctrineAccessToken
{
    #[ORM\Id]
    #[ORM\Column(length: 36, options: ['fixed' => true])]
    private string $id;

    #[ORM\Column(length: 36, options: ['fixed' => true])]
    private string $userId;

    #[ORM\Column(length: 64, options: ['fixed' => true])]
    private string $tokenHash;

    #[ORM\Column(length: 16)]
    private string $purpose;

    #[ORM\Column(options: ['comment' => '(DC2Type:datetime_immutable)'])]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true, options: ['comment' => '(DC2Type:datetime_immutable)'])]
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
