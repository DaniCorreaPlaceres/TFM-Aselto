<?php

namespace App\Documento\Domain;

final class Document
{
    private const MEDIA_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        private readonly string $id,
        private readonly string $clientUserId,
        private readonly ?string $firmId,
        private readonly string $originalName,
        private readonly string $storedPath,
        private readonly string $mediaType,
        private readonly DocumentStatus $status,
        private readonly ?MovementDirection $direction,
        private readonly ?\DateTimeImmutable $issuedOn,
        private readonly ?string $partyName,
        private readonly ?string $taxId,
        private readonly ?int $baseCents,
        private readonly ?TaxRate $taxRate,
        private readonly ?int $quotaCents,
        private readonly ?int $totalCents,
        private readonly ?ProposalOrigin $proposal,
        private readonly \DateTimeImmutable $createdAt,
        private readonly Tax $tax = Tax::Vat,
    ) {
        if (null !== $taxRate && !$tax->accepts($taxRate)) {
            throw new InvalidTaxRate('The rate does not belong to the document tax.');
        }

        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id)) {
            throw new InvalidDocument('The id is not a valid UUID.');
        }

        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $clientUserId)) {
            throw new InvalidDocument('The client id is not a valid UUID.');
        }

        if (null !== $firmId && 1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $firmId)) {
            throw new InvalidDocument('The firm id is not a valid UUID.');
        }

        if ('' === $originalName || mb_strlen($originalName) > 180) {
            throw new InvalidDocument('The file name is empty or too long.');
        }

        if ('' === $storedPath || str_contains($storedPath, '..')) {
            throw new InvalidDocument('The stored path is not valid.');
        }

        if (!in_array($mediaType, self::MEDIA_TYPES, true)) {
            throw new InvalidDocument('The file type is not allowed.');
        }
    }

    public static function receive(
        string $id,
        string $clientUserId,
        ?string $firmId,
        string $originalName,
        string $storedPath,
        string $mediaType,
        \DateTimeImmutable $createdAt,
        Tax $tax = Tax::Vat,
    ): self {
        return new self(
            $id,
            $clientUserId,
            $firmId,
            $originalName,
            $storedPath,
            $mediaType,
            DocumentStatus::Uploaded,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $createdAt,
            $tax,
        );
    }

    public function propose(DocumentDetails $details, ProposalOrigin $origin): self
    {
        $this->assertTax($details);

        if (DocumentStatus::Uploaded !== $this->status && DocumentStatus::Extracting !== $this->status) {
            throw new InvalidDocument('The document can no longer be read.');
        }

        return new self(
            $this->id,
            $this->clientUserId,
            $this->firmId,
            $this->originalName,
            $this->storedPath,
            $this->mediaType,
            DocumentStatus::Draft,
            $details->direction(),
            $details->issuedOn(),
            $details->partyName(),
            $details->taxId(),
            $details->baseCents(),
            $details->taxRate(),
            $details->quotaCents(),
            $details->totalCents(),
            $origin,
            $this->createdAt,
            $this->tax,
        );
    }

    public function saveDraft(DocumentDetails $details): self
    {
        $this->assertEditable();

        return $this->with($details, DocumentStatus::Draft);
    }

    public function confirm(DocumentDetails $details): self
    {
        $this->assertEditable();

        $missing = [];

        if (null === $details->direction()) {
            $missing[] = 'direction';
        }

        if (null === $details->issuedOn()) {
            $missing[] = 'issuedOn';
        }

        if (null === $details->partyName() || '' === $details->partyName()) {
            $missing[] = 'partyName';
        }

        if (null === $details->taxId() || '' === $details->taxId()) {
            $missing[] = 'taxId';
        }

        if (null === $details->baseCents()) {
            $missing[] = 'base';
        }

        if (null === $details->taxRate()) {
            $missing[] = 'taxRate';
        }

        if ([] !== $missing) {
            throw new IncompleteDocument($missing);
        }

        return $this->with($details, DocumentStatus::Submitted);
    }

    public function isEditable(): bool
    {
        return DocumentStatus::Uploaded === $this->status
            || DocumentStatus::Extracting === $this->status
            || DocumentStatus::Draft === $this->status;
    }

    public function needsReview(): bool
    {
        return $this->isEditable();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function clientUserId(): string
    {
        return $this->clientUserId;
    }

    public function firmId(): ?string
    {
        return $this->firmId;
    }

    public function originalName(): string
    {
        return $this->originalName;
    }

    public function storedPath(): string
    {
        return $this->storedPath;
    }

    public function mediaType(): string
    {
        return $this->mediaType;
    }

    public function status(): DocumentStatus
    {
        return $this->status;
    }

    public function direction(): ?MovementDirection
    {
        return $this->direction;
    }

    public function issuedOn(): ?\DateTimeImmutable
    {
        return $this->issuedOn;
    }

    public function partyName(): ?string
    {
        return $this->partyName;
    }

    public function taxId(): ?string
    {
        return $this->taxId;
    }

    public function baseCents(): ?int
    {
        return $this->baseCents;
    }

    public function taxRate(): ?TaxRate
    {
        return $this->taxRate;
    }

    public function tax(): Tax
    {
        return $this->tax;
    }

    public function quotaCents(): ?int
    {
        return $this->quotaCents;
    }

    public function totalCents(): ?int
    {
        return $this->totalCents;
    }

    public function proposal(): ?ProposalOrigin
    {
        return $this->proposal;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function assertEditable(): void
    {
        if (!$this->isEditable()) {
            throw new InvalidDocument('The document can no longer be changed.');
        }
    }

    private function with(DocumentDetails $details, DocumentStatus $status): self
    {
        $this->assertTax($details);

        return new self(
            $this->id,
            $this->clientUserId,
            $this->firmId,
            $this->originalName,
            $this->storedPath,
            $this->mediaType,
            $status,
            $details->direction(),
            $details->issuedOn(),
            $details->partyName(),
            $details->taxId(),
            $details->baseCents(),
            $details->taxRate(),
            $details->quotaCents(),
            $details->totalCents(),
            $this->proposal,
            $this->createdAt,
            $this->tax,
        );
    }

    private function assertTax(DocumentDetails $details): void
    {
        if ($details->tax() !== $this->tax || (null !== $details->taxRate() && !$this->tax->accepts($details->taxRate()))) {
            throw new InvalidTaxRate('The details do not match the document tax.');
        }
    }
}
