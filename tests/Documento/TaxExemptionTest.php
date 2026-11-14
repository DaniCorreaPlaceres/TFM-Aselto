<?php

namespace App\Tests\Documento;

use App\Documento\Application\DocumentForm;
use App\Documento\Domain\Document;
use App\Documento\Domain\InvalidTaxExemption;
use App\Documento\Domain\ProposalOrigin;
use App\Documento\Domain\Tax;
use App\Documento\Infrastructure\Persistence\DocumentReviewData;
use App\Documento\Ui\ApiDocumentInput;
use App\Finanzas\Domain\QuarterMovement;
use App\Finanzas\Domain\QuarterSummary;
use App\Finanzas\Infrastructure\Persistence\QuarterData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class TaxExemptionTest extends TestCase
{
    /** @return iterable<string, array{Tax}> */
    public static function territories(): iterable
    {
        foreach (Tax::cases() as $tax) {
            yield $tax->value => [$tax];
        }
    }

    #[DataProvider('territories')]
    public function testExemptIncomeRetainsTerritoryAndWithholding(Tax $tax): void
    {
        $details = DocumentForm::read('ingreso', '2026-11-01', 'Ejemplo', '00000000T', '100', '', $tax, '', '15', true);
        self::assertTrue($details->isTaxExempt());
        self::assertSame($tax, $details->tax());
        self::assertNull($details->taxRate());
        self::assertSame(0, $details->quotaCents());
        self::assertSame(8500, $details->totalCents());
        $received = Document::receive('00000000-0000-4000-8000-000000000031', '00000000-0000-4000-8000-000000000014', null, 'synthetic.pdf', 'synthetic.pdf', 'application/pdf', new \DateTimeImmutable(), $tax);
        $confirmed = $received->confirm($details, new \DateTimeImmutable());
        self::assertTrue($confirmed->isTaxExempt());
        self::assertTrue($confirmed->amendPersonal($confirmed->clientUserId(), new \DateTimeImmutable())->isTaxExempt());
        self::assertTrue(DocumentReviewData::restoreDetails(DocumentReviewData::details($details))->isTaxExempt());
    }

    public function testZeroRateIsNotAnExemptionAndPartialExemptionNeedsABase(): void
    {
        $zero = DocumentForm::read('gasto', '', '', '', '100', '0', Tax::Igic);
        self::assertFalse($zero->isTaxExempt());
        self::assertSame(0, $zero->quotaCents());
        $exempt = DocumentForm::read('gasto', '', '', '', '', '', taxExempt: true);
        self::assertNull($exempt->quotaCents());
        self::assertNull($exempt->totalCents());
    }

    public function testExemptionRejectsEvenAZeroPercentage(): void
    {
        $this->expectException(InvalidTaxExemption::class);
        DocumentForm::read('gasto', '', '', '', '100', '0', Tax::Igic, taxExempt: true);
    }

    public function testExemptionRejectsSurcharge(): void
    {
        $this->expectException(InvalidTaxExemption::class);
        DocumentForm::read('gasto', '', '', '', '100', '', Tax::Vat, '5.2', taxExempt: true);
    }

    public function testExtractionCannotAssignAnExemption(): void
    {
        $document = Document::receive('00000000-0000-4000-8000-000000000031', '00000000-0000-4000-8000-000000000014', null, 'synthetic.pdf', 'synthetic.pdf', 'application/pdf', new \DateTimeImmutable());
        $this->expectException(InvalidTaxExemption::class);
        $document->propose(DocumentForm::read('gasto', '', '', '', '100', '', taxExempt: true), ProposalOrigin::Extracted);
    }

    public function testLegacySnapshotsDefaultToNotExemptAndNewSnapshotsRetainTheFlag(): void
    {
        $movement = new QuarterMovement('synthetic', new \DateTimeImmutable('2026-11-01'), true, 'Ejemplo', 10000, 'igic', 0, 0, 1500, 8500, true);
        $data = QuarterData::summary(new QuarterSummary([$movement]));
        self::assertTrue(QuarterData::restoreSummary($data)->movements[0]->taxExempt);
        unset($data[0]['taxExempt']);
        self::assertFalse(QuarterData::restoreSummary($data)->movements[0]->taxExempt);
        self::assertSame(8500, QuarterData::restoreSummary($data)->movements[0]->totalCents);
        $details = DocumentReviewData::details(DocumentForm::read('ingreso', '', '', '', '100', '7', Tax::Igic));
        unset($details['tax_exempt']);
        self::assertFalse(DocumentReviewData::restoreDetails($details)->isTaxExempt());
    }

    public function testApiRequiresAnActualBoolean(): void
    {
        $input = new ApiDocumentInput();
        $request = static fn (mixed $flag): Request => Request::create('/api/v1/documents/synthetic/draft', 'PUT', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['version' => 1, 'tax_exempt' => $flag], JSON_THROW_ON_ERROR));
        self::assertTrue($input->json($request(true), ApiDocumentInput::FIELDS)['tax_exempt']);
        $this->expectException(BadRequestHttpException::class);
        $input->json($request('true'), ApiDocumentInput::FIELDS);
    }
}
