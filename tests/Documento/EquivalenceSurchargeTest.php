<?php

namespace App\Tests\Documento;

use App\Cliente\Domain\ClientRepository;
use App\Documento\Application\ConfirmDocument;
use App\Documento\Application\DocumentForm;
use App\Documento\Application\DocumentWithholdingPolicy;
use App\Documento\Application\OpenClientDocument;
use App\Documento\Application\SaveDocumentDraft;
use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentNotFound;
use App\Documento\Domain\DocumentRepository;
use App\Documento\Domain\DocumentStatus;
use App\Documento\Domain\EquivalenceSurcharge;
use App\Documento\Domain\InvalidSurcharge;
use App\Documento\Domain\Tax;
use App\Documento\Infrastructure\Extraction\OpenAiProposalReader;
use App\Documento\Infrastructure\Extraction\SampleDocumentExtractor;
use App\Documento\Infrastructure\Persistence\DoctrineDocument;
use App\Shared\Domain\Transaction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EquivalenceSurchargeTest extends TestCase
{
    private const OWNER = '00000000-0000-4000-8000-000000000014';
    private const ID = '00000000-0000-4000-8000-000000000031';

    #[DataProvider('validRates')]
    public function testSurchargeIsCalculatedSeparatelyAndIncludedInTheTotal(string $vat, string $rate, string $base, int $quota, int $total): void
    {
        $details = DocumentForm::read('gasto', '', '', '', $base, $vat, Tax::Vat, $rate);
        self::assertSame($quota, $details->surchargeQuotaCents());
        self::assertSame($total, $details->totalCents());
    }

    /** @return iterable<string, array{string, string, string, int, int}> */
    public static function validRates(): iterable
    {
        yield 'general' => ['21', '5,2', '100', 520, 12620];
        yield 'reduced' => ['10', '1.4', '100', 140, 11140];
        yield 'super reduced' => ['4', '0,5', '100', 50, 10450];
        yield 'tobacco' => ['21', '1.75', '100', 175, 12275];
        yield 'round half up' => ['4', '0.5', '1', 1, 105];
        yield 'round down' => ['4', '0.5', '0,99', 0, 103];
        yield 'zero base' => ['21', '5.20', '0', 0, 0];
    }

    #[DataProvider('invalidCombinations')]
    public function testIncompatibleOrMalformedSurchargesAreRejected(string $direction, Tax $tax, string $vat, string $surcharge): void
    {
        $this->expectException(InvalidSurcharge::class);
        DocumentForm::read($direction, '', '', '', '100', $vat, $tax, $surcharge);
    }

    /** @return iterable<string, array{string, Tax, string, string}> */
    public static function invalidCombinations(): iterable
    {
        yield 'income' => ['ingreso', Tax::Vat, '21', '5.2'];
        yield 'missing direction' => ['', Tax::Vat, '21', '5.2'];
        yield 'missing VAT' => ['gasto', Tax::Vat, '', '5.2'];
        yield 'zero VAT' => ['gasto', Tax::Vat, '0', '0.5'];
        yield 'wrong pairing' => ['gasto', Tax::Vat, '10', '5.2'];
        yield 'wrong tobacco VAT' => ['gasto', Tax::Vat, '4', '1.75'];
        yield 'IGIC' => ['gasto', Tax::Igic, '7', '5.2'];
        yield 'IPSI' => ['gasto', Tax::Ipsi, '21', '5.2'];
        foreach (['0', '-1', '2', '5.200', '5.2abc', '1e1', 'abc', '5,2.0'] as $rate) {
            yield $rate => ['gasto', Tax::Vat, '21', $rate];
        }
    }

    public function testPartialDraftAndRemovingASurcharge(): void
    {
        $partial = $this->document()->saveDraft(DocumentForm::read('gasto', '', '', '', '', '21', Tax::Vat, '5.2'));
        self::assertSame(EquivalenceSurcharge::General, $partial->surcharge());
        self::assertNull($partial->surchargeQuotaCents());
        self::assertNull($partial->totalCents());
        $removed = $partial->saveDraft(DocumentForm::read('ingreso', '', '', '', '100', '21'));
        self::assertNull($removed->surcharge());
        self::assertNull($removed->surchargeQuotaCents());
        self::assertSame(12100, $removed->totalCents());
    }

    public function testUseCasesSaveConfirmAndRestoreTheSurcharge(): void
    {
        $current = $this->document();
        $repository = $this->createMock(DocumentRepository::class);
        $repository->method('find')->willReturnCallback(static function () use (&$current): Document { return $current; });
        $repository->expects($this->exactly(2))->method('save')->willReturnCallback(static function (Document $document) use (&$current): void { $current = $document; });
        $transaction = $this->createStub(Transaction::class);
        $transaction->method('run')->willReturnCallback(static function (callable $operation): void { $operation(); });
        $open = new OpenClientDocument($repository);
        $draft = (new SaveDocumentDraft($open, $repository, $transaction, new DocumentWithholdingPolicy($this->createStub(ClientRepository::class))))->execute(self::OWNER, self::ID, 'gasto', '2026-11-08', 'Proveedor de ejemplo', '00000000T', '100', '21', '5.2');
        self::assertSame(DocumentStatus::Draft, $draft->status());
        $confirmed = (new ConfirmDocument($open, $repository, $transaction, new DocumentWithholdingPolicy($this->createStub(ClientRepository::class))))->execute(self::OWNER, self::ID, 'gasto', '2026-11-08', 'Proveedor de ejemplo', '00000000T', '100', '21', '5,2');
        self::assertSame(DocumentStatus::Submitted, $confirmed->status());
        self::assertSame(12620, $confirmed->totalCents());
        $record = new DoctrineDocument();
        $record->apply($confirmed);
        self::assertEquals($confirmed, $record->toDomain());
    }

    public function testAnotherOwnerCannotChangeTheSurcharge(): void
    {
        $repository = $this->createStub(DocumentRepository::class);
        $repository->method('find')->willReturn($this->document());
        $this->expectException(DocumentNotFound::class);
        (new SaveDocumentDraft(new OpenClientDocument($repository), $repository, $this->createStub(Transaction::class), new DocumentWithholdingPolicy($this->createStub(ClientRepository::class))))->execute('00000000-0000-4000-8000-000000000015', self::ID, 'gasto', '', '', '', '100', '21', '5.2');
    }

    public function testExtractionOnlyAcceptsAnExplicitCompatibleSurchargeAndIgnoresModelAmounts(): void
    {
        $reader = new OpenAiProposalReader();
        $details = $reader->read('{"direction":"expense","base":"100","tax_rate":21,"surcharge_rate":5.2,"surcharge_quota":999,"total":999}');
        self::assertSame(520, $details->surchargeQuotaCents());
        self::assertSame(12620, $details->totalCents());
        foreach ([null, 'wrong', 1.4, true, [], '5.200'] as $rate) {
            $details = $reader->read(json_encode(['direction' => 'expense', 'base' => '100', 'tax_rate' => 21, 'surcharge_rate' => $rate], JSON_THROW_ON_ERROR));
            self::assertNull($details->surcharge());
            self::assertSame(12100, $details->totalCents());
        }
        self::assertNull($reader->read('{"direction":"income","base":"100","tax_rate":21,"surcharge_rate":5.2}')->surcharge());
        self::assertNull($reader->read('{"direction":"expense","base":"100","tax_rate":7,"surcharge_rate":5.2}', Tax::Igic)->surcharge());
        self::assertNull($reader->read('{"direction":"expense","base":"100","tax_rate":21,"surcharge_rate":5.2}', Tax::Ipsi)->surcharge());
        self::assertNull($reader->read('{"direction":"expense","base":"100","tax_rate":21}')->surcharge());
        self::assertNull((new SampleDocumentExtractor())->propose('image/png', 'png')->details()->surcharge());
    }

    private function document(): Document
    {
        return Document::receive(self::ID, self::OWNER, null, 'ticket.png', 'documents/ticket.png', 'image/png', new \DateTimeImmutable('2026-11-08 10:00:00'));
    }
}
