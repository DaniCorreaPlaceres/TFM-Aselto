<?php

namespace App\Tests\Documento;

use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
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
use App\Documento\Domain\InvalidWithholding;
use App\Documento\Domain\Tax;
use App\Documento\Domain\WithholdingRate;
use App\Documento\Infrastructure\Extraction\OpenAiDocumentExtractor;
use App\Documento\Infrastructure\Extraction\OpenAiProposalReader;
use App\Documento\Infrastructure\Extraction\SampleDocumentExtractor;
use App\Documento\Infrastructure\Persistence\DoctrineDocument;
use App\Shared\Domain\Transaction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class IncomeWithholdingTest extends TestCase
{
    private const OWNER = '00000000-0000-4000-8000-000000000014';
    private const ID = '00000000-0000-4000-8000-000000000031';

    #[DataProvider('validAmounts')]
    public function testTheQuotaIsRoundedAndSubtractedFromTheTotal(Tax $tax, string $taxRate, string $base, string $rate, int $quota, int $total): void
    {
        $details = DocumentForm::read('ingreso', '', '', '', $base, $taxRate, $tax, '', $rate);
        self::assertSame($quota, $details->withholdingQuotaCents());
        self::assertSame($total, $details->totalCents());
    }

    /** @return iterable<string, array{Tax, string, string, string, int, int}> */
    public static function validAmounts(): iterable
    {
        yield '15 percent' => [Tax::Vat, '21', '100', '15', 1500, 10600];
        yield '7 percent' => [Tax::Vat, '21', '100', '7', 700, 11400];
        yield 'decimal' => [Tax::Vat, '21', '100', '7,25', 725, 11375];
        yield 'IGIC' => [Tax::Igic, '7', '100', '7', 700, 10000];
        yield 'IPSI' => [Tax::Ipsi, '2.35', '100', '15', 1500, 8735];
        yield 'half cent' => [Tax::Vat, '0', '0,10', '15', 2, 8];
        yield 'below half cent' => [Tax::Vat, '0', '0,09', '15', 1, 8];
        yield 'explicit zero' => [Tax::Vat, '21', '100', '0', 0, 12100];
        yield 'upper technical limit' => [Tax::Vat, '0', '100', '100.00', 10000, 0];
        yield 'zero base' => [Tax::Vat, '21', '0', '15', 0, 0];
    }

    #[DataProvider('invalidRates')]
    public function testMalformedAndOutOfRangeRatesAreRejected(string $rate): void
    {
        $this->expectException(InvalidWithholding::class);
        WithholdingRate::fromPercentage($rate);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidRates(): iterable
    {
        foreach (['-1', '100.01', '101', '7.250', '15abc', 'abc', '1e1', 'NaN', 'Infinity', '+7', '7%', '1,2.3', ''] as $rate) {
            yield 'rate '.$rate => [$rate];
        }
    }

    public function testExpenseCannotCarryWithholdingEvenAtZeroPercent(): void
    {
        $this->expectException(InvalidWithholding::class);
        DocumentForm::read('gasto', '', '', '', '100', '21', Tax::Vat, '', '0');
    }

    public function testDraftCanBePartialAndWithholdingCanBeRemoved(): void
    {
        $partial = $this->document()->saveDraft(DocumentForm::read('ingreso', '', '', '', '', '21', Tax::Vat, '', '15'));
        self::assertSame('15', $partial->withholdingRate()?->percentage());
        self::assertNull($partial->withholdingQuotaCents());
        self::assertNull($partial->totalCents());
        $removed = $partial->saveDraft(DocumentForm::read('gasto', '', '', '', '100', '21'));
        self::assertNull($removed->withholdingRate());
        self::assertNull($removed->withholdingQuotaCents());
        self::assertSame(12100, $removed->totalCents());
    }

    public function testUseCasesAndPersistenceKeepThePercentageQuotaAndState(): void
    {
        [$draft, $confirm] = $this->useCases(ClientKind::SelfEmployed);
        $saved = $draft->execute(self::OWNER, self::ID, 'ingreso', '2026-11-08', 'Cliente de ejemplo', '00000000T', '100', '21', '', '15');
        self::assertSame(DocumentStatus::Draft, $saved->status());
        $record = new DoctrineDocument();
        $record->apply($saved);
        self::assertEquals($saved, $record->toDomain());
        $saved = $confirm->execute(self::OWNER, self::ID, 'ingreso', '2026-11-08', 'Cliente de ejemplo', '00000000T', '100', '21', '', '15');
        self::assertSame(DocumentStatus::Submitted, $saved->status());
        self::assertSame(1500, $saved->withholdingQuotaCents());
        self::assertSame(10600, $saved->totalCents());
        $record->apply($saved);
        self::assertEquals($saved, $record->toDomain());
    }

    #[DataProvider('companyAttempts')]
    public function testCompaniesCannotInjectWithholding(bool $confirm, string $rate): void
    {
        [$draft, $confirmation] = $this->useCases(ClientKind::Company);
        $this->expectException(InvalidWithholding::class);
        ($confirm ? $confirmation : $draft)->execute(self::OWNER, self::ID, 'ingreso', '2026-11-08', 'Cliente de ejemplo', '00000000T', '100', '21', '', $rate);
    }

    /** @return iterable<string, array{bool, string}> */
    public static function companyAttempts(): iterable
    {
        yield 'draft' => [false, '15'];
        yield 'confirmation' => [true, '15'];
        yield 'zero draft' => [false, '0'];
        yield 'zero confirmation' => [true, '0'];
    }

    public function testCompanyIncomeWithoutWithholdingStillWorks(): void
    {
        [$draft] = $this->useCases(ClientKind::Company);
        self::assertSame(12100, $draft->execute(self::OWNER, self::ID, 'ingreso', '', '', '', '100', '21')->totalCents());
    }

    public function testAnotherOwnerCannotSetWithholding(): void
    {
        [$draft] = $this->useCases(ClientKind::SelfEmployed);
        $this->expectException(DocumentNotFound::class);
        $draft->execute('00000000-0000-4000-8000-000000000015', self::ID, 'ingreso', '', '', '', '100', '21', '', '15');
    }

    public function testMissingProfileDoesNotGrantWithholdingPermission(): void
    {
        $policy = new DocumentWithholdingPolicy($this->createStub(ClientRepository::class));
        self::assertFalse($policy->allows(self::OWNER));
        $this->expectException(InvalidWithholding::class);
        $policy->assertAllowed(self::OWNER, WithholdingRate::fromPercentage('15'));
    }

    public function testExtractionOnlyKeepsExplicitValidIncomeWithholdingForSelfEmployedClients(): void
    {
        $reader = new OpenAiProposalReader();
        $payload = '{"direction":"income","base":"100","tax_rate":21,"withholding_rate":15,"withholding_quota":999,"total":999}';
        $details = $reader->read($payload, Tax::Vat, true);
        self::assertSame(1500, $details->withholdingQuotaCents());
        self::assertSame(10600, $details->totalCents());
        self::assertNull($reader->read($payload, Tax::Vat, false)->withholdingRate());
        self::assertNull($reader->read(str_replace('income', 'expense', $payload), Tax::Vat, true)->withholdingRate());
        foreach (['wrong', '7.250', -1, 101, true, [], null] as $rate) {
            $details = $reader->read(json_encode(['direction' => 'income', 'base' => '100', 'tax_rate' => 21, 'withholding_rate' => $rate], JSON_THROW_ON_ERROR), Tax::Vat, true);
            self::assertNull($details->withholdingRate());
            self::assertSame(12100, $details->totalCents());
        }
        self::assertNull($reader->read('{"direction":"income","base":"100","tax_rate":21}', Tax::Vat, true)->withholdingRate());
        self::assertNull((new SampleDocumentExtractor())->propose('image/png', 'png', Tax::Vat, true)->details()->withholdingRate());
    }

    public function testTheAdapterReceivesTrustedClientContext(): void
    {
        $body = '';
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): MockResponse {
            $body = (string) ($options['body'] ?? '');

            return new MockResponse(json_encode(['choices' => [['message' => ['content' => '{"direction":"income","base":"100","tax_rate":21,"withholding_rate":15}']]]], JSON_THROW_ON_ERROR));
        });
        $extractor = new OpenAiDocumentExtractor($client, new OpenAiProposalReader(), 'test-key', 'test-model');
        self::assertSame(10600, $extractor->propose('image/png', 'png', Tax::Vat, true)->details()->totalCents());
        self::assertStringContainsString('The client is self-employed.', $body);
        self::assertNull($extractor->propose('image/png', 'png', Tax::Vat, false)->details()->withholdingRate());
        self::assertStringContainsString('Always return null for withholding_rate.', $body);
    }

    /** @return array{SaveDocumentDraft, ConfirmDocument} */
    private function useCases(ClientKind $kind): array
    {
        $current = $this->document();
        $repository = $this->createStub(DocumentRepository::class);
        $repository->method('find')->willReturnCallback(static function () use (&$current): Document { return $current; });
        $repository->method('save')->willReturnCallback(static function (Document $document) use (&$current): void { $current = $document; });
        $clients = $this->createStub(ClientRepository::class);
        $clients->method('findByUserId')->willReturn(new Client('00000000-0000-4000-8000-000000000021', self::OWNER, $kind, 'Cliente de ejemplo', null));
        $policy = new DocumentWithholdingPolicy($clients);
        $transaction = $this->createStub(Transaction::class);
        $transaction->method('run')->willReturnCallback(static function (callable $operation): void { $operation(); });
        $open = new OpenClientDocument($repository);

        return [new SaveDocumentDraft($open, $repository, $transaction, $policy), new ConfirmDocument($open, $repository, $transaction, $policy)];
    }

    private function document(): Document
    {
        return Document::receive(self::ID, self::OWNER, null, 'ticket.png', 'documents/ticket.png', 'image/png', new \DateTimeImmutable('2026-11-08 21:00:00'));
    }
}
