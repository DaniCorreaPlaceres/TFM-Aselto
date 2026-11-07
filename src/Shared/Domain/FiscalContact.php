<?php

namespace App\Shared\Domain;

final class FiscalContact
{
    private function __construct(
        private readonly string $taxId,
        private readonly string $phone,
        private readonly string $street,
        private readonly string $postalCode,
        private readonly string $municipality,
        private readonly string $province,
        private readonly bool $complete,
    ) {
    }

    public static function blank(): self
    {
        return new self('', '', '', '', '', '', false);
    }

    public static function required(
        string $taxId,
        string $phone,
        string $street,
        string $postalCode,
        string $municipality,
        string $province,
    ): self {
        $street = trim($street);
        $municipality = trim($municipality);
        $province = trim($province);
        $postalCode = trim($postalCode);

        if ('' === $street) {
            throw new InvalidFiscalContact(InvalidFiscalContact::STREET_MISSING);
        }

        if (mb_strlen($street) > 120) {
            throw new InvalidFiscalContact(InvalidFiscalContact::STREET);
        }

        if ('' === $municipality) {
            throw new InvalidFiscalContact(InvalidFiscalContact::MUNICIPALITY_MISSING);
        }

        if (mb_strlen($municipality) > 80) {
            throw new InvalidFiscalContact(InvalidFiscalContact::MUNICIPALITY);
        }

        if (null === SpanishProvince::codeFor($province)) {
            throw new InvalidFiscalContact(InvalidFiscalContact::PROVINCE);
        }

        if ('' === $postalCode) {
            throw new InvalidFiscalContact(InvalidFiscalContact::POSTAL_CODE_MISSING);
        }

        if (1 !== preg_match('/^\d{5}$/', $postalCode)) {
            throw new InvalidFiscalContact(InvalidFiscalContact::POSTAL_CODE);
        }

        if (substr($postalCode, 0, 2) !== SpanishProvince::codeFor($province)) {
            throw new InvalidFiscalContact(InvalidFiscalContact::POSTAL_CODE_MISMATCH);
        }

        return new self(
            SpanishTaxId::normalize($taxId),
            self::normalizePhone($phone),
            $street,
            $postalCode,
            $municipality,
            $province,
            true,
        );
    }

    public static function stored(
        string $taxId,
        string $phone,
        string $street,
        string $postalCode,
        string $municipality,
        string $province,
    ): self {
        if ('' === $taxId && '' === $phone && '' === $street && '' === $postalCode && '' === $municipality && '' === $province) {
            return self::blank();
        }

        return self::required($taxId, $phone, $street, $postalCode, $municipality, $province);
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    public function taxId(): string
    {
        return $this->taxId;
    }

    public function phone(): string
    {
        return $this->phone;
    }

    public function street(): string
    {
        return $this->street;
    }

    public function postalCode(): string
    {
        return $this->postalCode;
    }

    public function municipality(): string
    {
        return $this->municipality;
    }

    public function province(): string
    {
        return $this->province;
    }

    private static function normalizePhone(string $value): string
    {
        $value = str_replace([' ', '-', '.'], '', trim($value));

        if (str_starts_with($value, '+34')) {
            $value = substr($value, 3);
        } elseif (str_starts_with($value, '0034')) {
            $value = substr($value, 4);
        }

        if ('' === $value) {
            throw new InvalidFiscalContact(InvalidFiscalContact::PHONE_MISSING);
        }

        if (1 !== preg_match('/^[6-9]\d{8}$/', $value)) {
            throw new InvalidFiscalContact(InvalidFiscalContact::PHONE);
        }

        return $value;
    }
}
