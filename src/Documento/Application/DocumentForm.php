<?php

namespace App\Documento\Application;

use App\Documento\Domain\DocumentDetails;
use App\Documento\Domain\EquivalenceSurcharge;
use App\Documento\Domain\InvalidDocument;
use App\Documento\Domain\MovementDirection;
use App\Documento\Domain\Tax;
use App\Documento\Domain\TaxRate;

final class DocumentForm
{
    public static function read(
        string $direction,
        string $issuedOn,
        string $partyName,
        string $taxId,
        string $base,
        string $taxRate,
        Tax $tax = Tax::Vat,
        string $surcharge = '',
    ): DocumentDetails {
        return new DocumentDetails(
            self::direction($direction),
            self::date($issuedOn),
            self::text($partyName),
            self::taxId($taxId),
            self::money($base),
            '' === trim($taxRate) ? null : TaxRate::fromPercentage($tax, $taxRate),
            $tax,
            '' === trim($surcharge) ? null : EquivalenceSurcharge::fromPercentage($surcharge),
        );
    }

    private static function direction(string $value): ?MovementDirection
    {
        return match ($value) {
            '' => null,
            'ingreso' => MovementDirection::Income,
            'gasto' => MovementDirection::Expense,
            default => throw new InvalidDocument('The direction is not valid.'),
        };
    }

    private static function date(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);

        if ('' === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (!$date instanceof \DateTimeImmutable || $date->format('Y-m-d') !== $value) {
            throw new InvalidDocument('The date is not valid.');
        }

        return $date;
    }

    private static function text(string $value): ?string
    {
        $value = trim($value);

        return '' === $value ? null : $value;
    }

    private static function taxId(string $value): ?string
    {
        $value = mb_strtoupper(trim($value));

        return '' === $value ? null : $value;
    }

    private static function money(string $value): ?int
    {
        $value = str_replace([' ', "\u{00a0}"], '', trim($value));

        if ('' === $value) {
            return null;
        }

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        if (1 !== preg_match('/^\d{1,9}(\.\d{1,2})?$/', $value)) {
            throw new InvalidDocument('The amount is not valid.');
        }

        [$euros, $cents] = array_pad(explode('.', $value, 2), 2, '0');

        return ((int) $euros * 100) + (int) str_pad($cents, 2, '0');
    }
}
