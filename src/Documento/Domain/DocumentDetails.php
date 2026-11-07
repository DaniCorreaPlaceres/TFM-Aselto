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
        private readonly ?VatRate $vatRate,
    ) {
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

    public function vatRate(): ?VatRate
    {
        return $this->vatRate;
    }

    public function quotaCents(): ?int
    {
        if (null === $this->baseCents || null === $this->vatRate) {
            return null;
        }

        return $this->vatRate->quotaFor($this->baseCents);
    }

    public function totalCents(): ?int
    {
        $quota = $this->quotaCents();

        if (null === $this->baseCents || null === $quota) {
            return null;
        }

        return $this->baseCents + $quota;
    }
}
