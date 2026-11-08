<?php

namespace App\Documento\Domain;

final class DocumentDetails
{
    public function __construct(
        private readonly ?MovementDirection $direction,
        private readonly ?\DateTimeImmutable $issuedOn,
        private readonly ?string $partyName,
        private readonly ?string $taxId,
        private readonly ?int $baseCents,
        private readonly ?TaxRate $taxRate,
        private readonly Tax $tax = Tax::Vat,
        private readonly ?EquivalenceSurcharge $surcharge = null,
        private readonly ?WithholdingRate $withholdingRate = null,
    ) {
        if (null !== $withholdingRate && MovementDirection::Income !== $direction) {
            throw new InvalidWithholding('Withholding is only available for income.');
        }

        if (null !== $surcharge && !$surcharge->supports($direction, $tax, $taxRate)) {
            throw new InvalidSurcharge('The surcharge is not compatible with the document.');
        }

        if (null !== $taxRate && !$tax->accepts($taxRate)) {
            throw new InvalidTaxRate('The rate does not belong to the document tax.');
        }

        if (null !== $partyName && mb_strlen($partyName) > 120) {
            throw new InvalidDocument('The party name is too long.');
        }

        if (null !== $taxId && mb_strlen($taxId) > 20) {
            throw new InvalidDocument('The tax id is too long.');
        }

        if (null !== $baseCents && $baseCents < 0) {
            throw new InvalidDocument('The base is negative.');
        }
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
        if (null === $this->baseCents || null === $this->taxRate) {
            return null;
        }

        return $this->taxRate->quotaFor($this->baseCents);
    }

    public function surcharge(): ?EquivalenceSurcharge
    {
        return $this->surcharge;
    }

    public function surchargeQuotaCents(): ?int
    {
        return null === $this->baseCents ? null : $this->surcharge?->quotaFor($this->baseCents);
    }

    public function withholdingRate(): ?WithholdingRate
    {
        return $this->withholdingRate;
    }

    public function withholdingQuotaCents(): ?int
    {
        return null === $this->baseCents ? null : $this->withholdingRate?->quotaFor($this->baseCents);
    }

    public function totalCents(): ?int
    {
        $quota = $this->quotaCents();

        if (null === $this->baseCents || null === $quota) {
            return null;
        }

        return $this->baseCents + $quota + ($this->surchargeQuotaCents() ?? 0) - ($this->withholdingQuotaCents() ?? 0);
    }
}
