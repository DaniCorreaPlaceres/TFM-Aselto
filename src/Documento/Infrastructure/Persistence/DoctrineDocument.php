<?php

namespace App\Documento\Infrastructure\Persistence;

use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentStatus;
use App\Documento\Domain\MovementDirection;
use App\Documento\Domain\VatRate;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'client_document')]
class DoctrineDocument
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $id;

    #[ORM\Column(length: 36)]
    private string $clientUserId;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $firmId = null;

    #[ORM\Column(length: 180)]
    private string $originalName;

    #[ORM\Column(length: 255)]
    private string $storedPath;

    #[ORM\Column(length: 64)]
    private string $mediaType;

    #[ORM\Column(length: 16)]
    private string $status;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $direction = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $issuedOn = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $partyName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $taxId = null;

    #[ORM\Column(nullable: true)]
    private ?int $baseCents = null;

    #[ORM\Column(nullable: true)]
    private ?int $vatRate = null;

    #[ORM\Column(nullable: true)]
    private ?int $quotaCents = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalCents = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function apply(Document $document): void
    {
        $this->id = $document->id();
        $this->clientUserId = $document->clientUserId();
        $this->firmId = $document->firmId();
        $this->originalName = $document->originalName();
        $this->storedPath = $document->storedPath();
        $this->mediaType = $document->mediaType();
        $this->status = $document->status()->value;
        $this->direction = $document->direction()?->value;
        $this->issuedOn = $document->issuedOn();
        $this->partyName = $document->partyName();
        $this->taxId = $document->taxId();
        $this->baseCents = $document->baseCents();
        $this->vatRate = $document->vatRate()?->value;
        $this->quotaCents = $document->quotaCents();
        $this->totalCents = $document->totalCents();
        $this->createdAt = $document->createdAt();
    }

    public function toDomain(): Document
    {
        return new Document(
            $this->id,
            $this->clientUserId,
            $this->firmId,
            $this->originalName,
            $this->storedPath,
            $this->mediaType,
            DocumentStatus::from($this->status),
            null === $this->direction ? null : MovementDirection::from($this->direction),
            $this->issuedOn,
            $this->partyName,
            $this->taxId,
            $this->baseCents,
            null === $this->vatRate ? null : VatRate::from($this->vatRate),
            $this->quotaCents,
            $this->totalCents,
            $this->createdAt,
        );
    }
}
