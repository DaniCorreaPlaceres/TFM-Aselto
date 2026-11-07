<?php

namespace App\Shared\Domain;

final class SpanishTaxId
{
    private const NIF_LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    private const CIF_LETTERS = 'JABCDEFGHI';

    public static function normalize(string $value): string
    {
        $value = strtoupper(str_replace([' ', '-'], '', trim($value)));

        if (self::isNif($value) || self::isNie($value) || self::isCif($value)) {
            return $value;
        }

        throw new InvalidFiscalContact('' === $value ? InvalidFiscalContact::TAX_ID_MISSING : InvalidFiscalContact::TAX_ID);
    }

    private static function isNif(string $value): bool
    {
        if (1 !== preg_match('/^\d{8}[A-Z]$/', $value)) {
            return false;
        }

        return self::NIF_LETTERS[(int) substr($value, 0, 8) % 23] === $value[8];
    }

    private static function isNie(string $value): bool
    {
        if (1 !== preg_match('/^[XYZ]\d{7}[A-Z]$/', $value)) {
            return false;
        }

        $number = str_replace(['X', 'Y', 'Z'], ['0', '1', '2'], $value);

        return self::isNif($number);
    }

    private static function isCif(string $value): bool
    {
        if (1 !== preg_match('/^[ABCDEFGHJNPQRSUVW]\d{7}[0-9A-J]$/', $value)) {
            return false;
        }

        $sum = 0;

        for ($index = 0; $index < 7; ++$index) {
            $digit = (int) $value[$index + 1];

            if (0 === $index % 2) {
                $digit *= 2;
                $sum += intdiv($digit, 10) + ($digit % 10);
            } else {
                $sum += $digit;
            }
        }

        $control = (10 - ($sum % 10)) % 10;
        $expectedDigit = (string) $control;
        $expectedLetter = self::CIF_LETTERS[$control];
        $actual = $value[8];
        $kind = $value[0];

        if (in_array($kind, ['A', 'B', 'E', 'H'], true)) {
            return $actual === $expectedDigit;
        }

        if (in_array($kind, ['N', 'P', 'Q', 'R', 'S', 'W'], true)) {
            return $actual === $expectedLetter;
        }

        return $actual === $expectedDigit || $actual === $expectedLetter;
    }
}
