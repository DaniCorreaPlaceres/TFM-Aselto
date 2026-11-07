<?php

namespace App\Tests\Documento;

use App\Documento\Domain\ExtractionFailed;
use App\Documento\Domain\MovementDirection;
use App\Documento\Domain\ProposalOrigin;
use App\Documento\Domain\VatRate;
use App\Documento\Infrastructure\Extraction\OpenAiDocumentExtractor;
use App\Documento\Infrastructure\Extraction\OpenAiProposalReader;
use App\Documento\Infrastructure\Extraction\SampleDocumentExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DocumentReadingTest extends TestCase
{
    public function testTheSampleProposalIsMarkedAsAnExample(): void
    {
        $proposal = (new SampleDocumentExtractor())->propose('image/png', 'png');

        self::assertSame(ProposalOrigin::Sample, $proposal->origin());
        self::assertSame('Proveedor de ejemplo', $proposal->details()->partyName());
        self::assertSame(12100, $proposal->details()->totalCents());
    }

    public function testTheReaderMapsAPayloadAndIgnoresTheModelTotal(): void
    {
        $details = (new OpenAiProposalReader())->read(json_encode([
            'direction' => 'expense',
            'issued_on' => '2026-01-02',
            'party_name' => 'Taller',
            'tax_id' => '00000000t',
            'base' => '50.00',
            'vat_rate' => 10,
            'quota' => '999.00',
            'total' => '999.00',
        ], JSON_THROW_ON_ERROR));

        self::assertSame(MovementDirection::Expense, $details->direction());
        self::assertSame('2026-01-02', $details->issuedOn()?->format('Y-m-d'));
        self::assertSame('Taller', $details->partyName());
        self::assertSame('00000000T', $details->taxId());
        self::assertSame(5000, $details->baseCents());
        self::assertSame(VatRate::Reduced, $details->vatRate());
        self::assertSame(500, $details->quotaCents());
        self::assertSame(5500, $details->totalCents());
    }

    public function testTheReaderDropsAnUnknownVatRate(): void
    {
        $details = (new OpenAiProposalReader())->read('{"direction":"income","vat_rate":7,"base":"10,00"}');

        self::assertSame(MovementDirection::Income, $details->direction());
        self::assertNull($details->vatRate());
        self::assertNull($details->quotaCents());
        self::assertSame(1000, $details->baseCents());
    }

    public function testTheReaderRejectsAPayloadWithoutUsableFields(): void
    {
        $this->expectException(ExtractionFailed::class);

        (new OpenAiProposalReader())->read('{"direction":null,"vat_rate":7,"total":"10.00"}');
    }

    public function testTheVisionAdapterReturnsAnExtractedProposal(): void
    {
        $requests = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? ''];

            return new MockResponse(json_encode([
                'choices' => [[
                    'message' => ['content' => '{"direction":"expense","issued_on":"2026-01-02","party_name":"Taller","tax_id":"00000000T","base":"50.00","vat_rate":10}'],
                ]],
            ], JSON_THROW_ON_ERROR));
        });

        $proposal = $this->extractor($client, 'test-key')->propose('image/png', 'png');
        $body = (string) $requests[0]['body'];

        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('https://api.openai.com/v1/chat/completions', $requests[0]['url']);
        self::assertStringContainsString('image_url', $body);
        self::assertStringNotContainsString('test-key', $body);
        self::assertSame(ProposalOrigin::Extracted, $proposal->origin());
        self::assertSame(5500, $proposal->details()->totalCents());
    }

    public function testAPdfIsSentAsAFile(): void
    {
        $body = '';
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$body): MockResponse {
            $body = (string) ($options['body'] ?? '');

            return new MockResponse('{"choices":[{"message":{"content":"{\"direction\":\"expense\",\"party_name\":\"Taller\"}"}}]}');
        });

        $this->extractor($client, 'test-key')->propose('application/pdf', '%PDF');

        self::assertStringContainsString('"type":"file"', $body);
    }

    public function testAFailedCallDoesNotExposeTheKey(): void
    {
        $client = new MockHttpClient(new MockResponse('no', ['http_code' => 500]));

        try {
            $this->extractor($client, 'super-secret-key')->propose('image/png', 'png');
            self::fail('The failed call should not return a proposal.');
        } catch (ExtractionFailed $exception) {
            self::assertStringNotContainsString('super-secret-key', $exception->getMessage());
        }
    }

    private function extractor(MockHttpClient $client, string $apiKey): OpenAiDocumentExtractor
    {
        return new OpenAiDocumentExtractor($client, new OpenAiProposalReader(), $apiKey, 'gpt-4o-mini');
    }
}
