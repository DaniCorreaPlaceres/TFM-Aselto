<?php

namespace App\Documento\Infrastructure\Extraction;

use App\Documento\Application\DocumentForm;
use App\Documento\Domain\DocumentDetails;
use App\Documento\Domain\ExtractionFailed;
use App\Documento\Domain\InvalidDocument;

final class OpenAiProposalReader
{
    public function read(string $payload): DocumentDetails
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
                self::vatRate($data['vat_rate'] ?? null),
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
            && null === $details->vatRate()
        ) {
            throw new ExtractionFailed('The document could not be read.');
        }

        return $details;
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

    private static function vatRate(mixed $value): string
    {
        if (is_float($value) && $value === (float) (int) $value) {
            $value = (int) $value;
        }

        if (is_string($value) && 1 === preg_match('/^\d+$/', trim($value))) {
            $value = (int) trim($value);
        }

        if (!is_int($value) || !in_array($value, [0, 4, 10, 21], true)) {
            return '';
        }

        return (string) $value;
    }
}
