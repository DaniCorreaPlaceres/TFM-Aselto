<?php

namespace App\Documento\Infrastructure\Extraction;

use App\Documento\Application\DocumentForm;
use App\Documento\Domain\DocumentDetails;
use App\Documento\Domain\EquivalenceSurcharge;
use App\Documento\Domain\ExtractionFailed;
use App\Documento\Domain\InvalidDocument;
use App\Documento\Domain\InvalidSurcharge;
use App\Documento\Domain\InvalidTaxRate;
use App\Documento\Domain\InvalidWithholding;
use App\Documento\Domain\MovementDirection;
use App\Documento\Domain\Tax;
use App\Documento\Domain\TaxRate;
use App\Documento\Domain\WithholdingRate;

final class OpenAiProposalReader
{
    public function read(string $payload, Tax $tax = Tax::Vat, bool $allowsWithholding = false): DocumentDetails
    {
        $payload = trim($payload);
        $payload = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $payload) ?? $payload;
        $data = json_decode($payload, true);

        if (!is_array($data)) {
            throw new ExtractionFailed('The document could not be read.');
        }

        try {
            $details = DocumentForm::read(
                self::direction($data['direction'] ?? null),
                self::date($data['issued_on'] ?? null),
                self::text($data['party_name'] ?? null, 120),
                self::text($data['tax_id'] ?? null, 20),
                self::money($data['base'] ?? null),
                self::taxRate($data['tax_rate'] ?? null, $tax),
                $tax,
            );
        } catch (InvalidDocument) {
            throw new ExtractionFailed('The document could not be read.');
        }

        if (
            null === $details->direction()
            && null === $details->issuedOn()
            && null === $details->partyName()
            && null === $details->taxId()
            && null === $details->baseCents()
            && null === $details->taxRate()
        ) {
            throw new ExtractionFailed('The document could not be read.');
        }

        $value = $data['surcharge_rate'] ?? null;
        $surcharge = null;

        if (is_int($value) || is_float($value) || is_string($value)) {
            try {
                $candidate = EquivalenceSurcharge::fromPercentage((string) $value);
                if ($candidate->supports($details->direction(), $tax, $details->taxRate())) {
                    $surcharge = $candidate;
                }
            } catch (InvalidSurcharge) {
            }
        }

        $withholding = null;
        $value = $data['withholding_rate'] ?? null;
        if ($allowsWithholding && MovementDirection::Income === $details->direction() && (is_int($value) || is_float($value) || is_string($value))) {
            try {
                $withholding = WithholdingRate::fromPercentage((string) $value);
            } catch (InvalidWithholding) {
            }
        }

        return new DocumentDetails($details->direction(), $details->issuedOn(), $details->partyName(), $details->taxId(), $details->baseCents(), $details->taxRate(), $tax, $surcharge, $withholding);
    }

    private static function direction(mixed $value): string
    {
        return match (is_string($value) ? mb_strtolower(trim($value)) : '') {
            'income', 'ingreso' => 'ingreso',
            'expense', 'gasto' => 'gasto',
            default => '',
        };
    }

    private static function date(mixed $value): string
    {
        if (!is_string($value) || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : '';
    }

    private static function text(mixed $value, int $limit): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = trim($value);

        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit) : $value;
    }

    private static function money(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            if ($value < 0 || $value >= 1_000_000_000) {
                return '';
            }

            $value = number_format((float) $value, 2, '.', '');
        }

        if (!is_string($value)) {
            return '';
        }

        $value = trim($value);
        $normalized = str_replace([' ', "\u{00a0}"], '', $value);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        return 1 === preg_match('/^\d{1,9}(\.\d{1,2})?$/', $normalized) ? $value : '';
    }

    private static function taxRate(mixed $value, Tax $tax): string
    {
        if (Tax::Ipsi === $tax || (!is_int($value) && !is_float($value) && !is_string($value))) {
            return '';
        }

        try {
            return TaxRate::fromPercentage($tax, (string) $value)->percentage();
        } catch (InvalidTaxRate) {
            return '';
        }
    }
}
