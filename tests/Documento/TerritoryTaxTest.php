<?php

namespace App\Tests\Documento;

use App\Documento\Application\DocumentForm;
use App\Documento\Domain\Document;
use App\Documento\Domain\InvalidTaxRate;
use App\Documento\Domain\Tax;
use App\Documento\Domain\TaxRate;
use App\Documento\Infrastructure\Persistence\DoctrineDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TerritoryTaxTest extends TestCase
{
    #[DataProvider('acceptedRates')]
    public function testAcceptedRatesCalculateAndRoundInCents(Tax $tax, string $percentage, int $base, int $quota): void
    {
        $rate = TaxRate::fromPercentage($tax, $percentage);

        self::assertSame($quota, $rate->quotaFor($base));
    }

    /** @return iterable<string, array{Tax, string, int, int}> */
    public static function acceptedRates(): iterable
    {
        foreach ([0, 4, 10, 21] as $rate) {
            yield 'IVA '.$rate => [Tax::Vat, (string) $rate, 10000, $rate * 100];
        }

        foreach ([0, 3, 7, 9.5, 15, 20] as $rate) {
            yield 'IGIC '.$rate => [Tax::Igic, (string) $rate, 10000, (int) ($rate * 100)];
        }

        yield 'IGIC Spanish decimal' => [Tax::Igic, '9,50', 10000, 950];
        yield 'IPSI manual' => [Tax::Ipsi, '2,35', 10000, 235];
        yield 'IPSI zero' => [Tax::Ipsi, '0', 10000, 0];
        yield 'IPSI upper input limit' => [Tax::Ipsi, '100.00', 10000, 10000];
        yield 'round half cent up' => [Tax::Igic, '9.5', 100, 10];
        yield 'round below half cent down' => [Tax::Igic, '9.5', 99, 9];
        yield 'large amount' => [Tax::Ipsi, '99.99', 99999999999, 99989999999];
    }

    #[DataProvider('rejectedRates')]
    public function testInvalidRatesAreRejectedWithoutCoercion(Tax $tax, string $percentage): void
    {
        $this->expectException(InvalidTaxRate::class);
        TaxRate::fromPercentage($tax, $percentage);
    }

    /** @return iterable<string, array{Tax, string}> */
    public static function rejectedRates(): iterable
    {
        yield 'VAT in IGIC' => [Tax::Igic, '21'];
        yield 'IGIC in VAT' => [Tax::Vat, '7'];

        foreach (['-1', '101', '100.01', '9.500', '21abc', 'abc', '1e1', '1,2.3', 'NaN', 'Infinity', '+7', '7%'] as $rate) {
            yield $rate => [Tax::Ipsi, $rate];
        }
    }

    public function testTheDomainRejectsChangingTheDocumentTaxThroughDetails(): void
    {
        $document = $this->document(Tax::Igic);
        $this->expectException(InvalidTaxRate::class);
        $document->saveDraft(DocumentForm::read('gasto', '', '', '', '100', '21', Tax::Vat));
    }

    public function testPersistenceKeepsTaxDecimalRateAmountsAndStatus(): void
    {
        foreach ([Tax::Vat, Tax::Igic, Tax::Ipsi] as $tax) {
            $rate = match ($tax) {
                Tax::Vat => '21',
                Tax::Igic => '9,5',
                Tax::Ipsi => '2,35',
            };
            $document = $this->document($tax)->confirm(DocumentForm::read('gasto', '2026-03-12', 'Proveedor de ejemplo', '00000000T', '100', $rate, $tax));
            $record = new DoctrineDocument();
            $record->apply($document);
            $restored = $record->toDomain();

            self::assertEquals($document, $restored);
            self::assertSame($tax, $restored->tax());
        }
    }

    private function document(Tax $tax): Document
    {
        return Document::receive(
            '00000000-0000-4000-8000-000000000031',
            '00000000-0000-4000-8000-000000000014',
            null,
            'ticket.png',
            'documents/ticket.png',
            'image/png',
            new \DateTimeImmutable('2026-11-07 14:00:00 UTC'),
            $tax,
        );
    }
}
