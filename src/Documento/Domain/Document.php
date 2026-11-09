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

    /**
     * @param list<ReviewRecord>       $reviewHistory
     * @param list<SupportingDocument> $supportingFiles
     */
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
        private readonly ?EquivalenceSurcharge $surcharge = null,
        private readonly ?int $surchargeQuotaCents = null,
        private readonly ?WithholdingRate $withholdingRate = null,
        private readonly ?int $withholdingQuotaCents = null,
        private readonly array $reviewHistory = [],
        private readonly array $supportingFiles = [],
        private readonly ?string $pendingRequest = null,
        private readonly int $version = 1,
    ) {
        if (null !== $withholdingRate && MovementDirection::Income !== $direction) {
            throw new InvalidWithholding('Withholding is only available for income.');
        }

        if ((null === $baseCents ? null : $withholdingRate?->quotaFor($baseCents)) !== $withholdingQuotaCents) {
            throw new InvalidWithholding('The withholding quota does not match the base and percentage.');
        }

        if (null !== $surcharge && !$surcharge->supports($direction, $tax, $taxRate)) {
            throw new InvalidSurcharge('The surcharge is not compatible with the document.');
        }

        if ((null === $baseCents ? null : $surcharge?->quotaFor($baseCents)) !== $surchargeQuotaCents) {
            throw new InvalidSurcharge('The surcharge quota does not match the base and rate.');
        }

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
            $details->surcharge(),
            $details->surchargeQuotaCents(),
            $details->withholdingRate(),
            $details->withholdingQuotaCents(),
            $this->reviewHistory,
            $this->supportingFiles,
            $this->pendingRequest,
            $this->version,
        );
    }

    public function saveDraft(DocumentDetails $details): self
    {
        $this->assertEditable();

        return $this->with($details, DocumentStatus::Draft);
    }

    public function confirm(DocumentDetails $details, ?\DateTimeImmutable $submittedAt = null): self
    {
        $this->assertEditable();
        $this->assertComplete($details);
        $history = $this->reviewHistory;
        if (null !== $submittedAt) {
            $history[] = new ReviewRecord(ReviewAction::Submitted, $this->clientUserId, $submittedAt, null, $this->details(), $details);
        }

        return $this->with($details, DocumentStatus::Submitted, $history);
    }

    public function validate(DocumentDetails $details, string $actorId, \DateTimeImmutable $at): self
    {
        $this->assertSubmitted();
        if (null !== $this->pendingRequest) {
            throw new InvalidDocument('A supporting document is still required.');
        }
        $this->assertComplete($details);

        return $this->with($details, DocumentStatus::Validated, [...$this->reviewHistory, new ReviewRecord(ReviewAction::Validated, $actorId, $at, null, $this->details(), $details)]);
    }

    public function reject(string $reason, string $actorId, \DateTimeImmutable $at): self
    {
        $this->assertSubmitted();

        return $this->with($this->details(), DocumentStatus::Rejected, [...$this->reviewHistory, new ReviewRecord(ReviewAction::Rejected, $actorId, $at, trim($reason), $this->details())]);
    }

    public function requestSupportingDocument(string $reason, string $actorId, \DateTimeImmutable $at): self
    {
        $this->assertSubmitted();
        if (null !== $this->pendingRequest) {
            throw new InvalidDocument('A supporting document has already been requested.');
        }

        return $this->with($this->details(), DocumentStatus::Submitted, [...$this->reviewHistory, new ReviewRecord(ReviewAction::Requested, $actorId, $at, trim($reason), $this->details())], null, trim($reason));
    }

    public function attachSupportingDocument(SupportingDocument $file, string $actorId, \DateTimeImmutable $at): self
    {
        $this->assertSubmitted();
        if (null === $this->pendingRequest || $actorId !== $this->clientUserId) {
            throw new InvalidDocument('There is no supporting document request for this client.');
        }

        return $this->with($this->details(), DocumentStatus::Submitted, [...$this->reviewHistory, new ReviewRecord(ReviewAction::Attached, $actorId, $at, $this->pendingRequest, $this->details(), null, $file->id)], [...$this->supportingFiles, $file]);
    }

    private function assertComplete(DocumentDetails $details): void
    {
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
    }

    private function assertSubmitted(): void
    {
        if (DocumentStatus::Submitted !== $this->status || null === $this->firmId) {
            throw new InvalidDocument('Only submitted documents linked to a firm can be reviewed.');
        }
    }

    public function isEditable(): bool
    {
        return DocumentStatus::Uploaded === $this->status
            || DocumentStatus::Extracting === $this->status
            || DocumentStatus::Draft === $this->status
            || DocumentStatus::Rejected === $this->status;
    }

    public function needsReview(): bool
    {
        return $this->isEditable() || null !== $this->pendingRequest;
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

    public function surcharge(): ?EquivalenceSurcharge
    {
        return $this->surcharge;
    }

    public function surchargeQuotaCents(): ?int
    {
        return $this->surchargeQuotaCents;
    }

    public function withholdingRate(): ?WithholdingRate
    {
        return $this->withholdingRate;
    }

    public function withholdingQuotaCents(): ?int
    {
        return $this->withholdingQuotaCents;
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

    public function details(): DocumentDetails
    {
        return new DocumentDetails($this->direction, $this->issuedOn, $this->partyName, $this->taxId, $this->baseCents, $this->taxRate, $this->tax, $this->surcharge, $this->withholdingRate);
    }

    /** @return list<ReviewRecord> */
    public function reviewHistory(): array
    {
        return $this->reviewHistory;
    }

    /** @return list<SupportingDocument> */
    public function supportingFiles(): array
    {
        return $this->supportingFiles;
    }

    public function pendingRequest(): ?string
    {
        return $this->pendingRequest;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function assertVersion(int $version): void
    {
        if ($this->version !== $version) {
            throw new DocumentChanged('The document has changed since it was opened.');
        }
    }

    private function assertEditable(): void
    {
        if (!$this->isEditable()) {
            throw new InvalidDocument('The document can no longer be changed.');
        }
    }

    /**
     * @param list<ReviewRecord>|null       $history
     * @param list<SupportingDocument>|null $files
     */
    private function with(DocumentDetails $details, DocumentStatus $status, ?array $history = null, ?array $files = null, ?string $pendingRequest = null): self
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
            $details->surcharge(),
            $details->surchargeQuotaCents(),
            $details->withholdingRate(),
            $details->withholdingQuotaCents(),
            $history ?? $this->reviewHistory,
            $files ?? $this->supportingFiles,
            $pendingRequest,
            $this->version,
        );
    }

    private function assertTax(DocumentDetails $details): void
    {
        if ($details->tax() !== $this->tax || (null !== $details->taxRate() && !$this->tax->accepts($details->taxRate()))) {
            throw new InvalidTaxRate('The details do not match the document tax.');
        }
    }
}
