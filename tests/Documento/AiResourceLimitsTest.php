<?php

namespace App\Tests\Documento;

use App\Documento\Domain\ExtractionFailed;
use App\Documento\Infrastructure\Extraction\OpenAiDocumentExtractor;
use App\Documento\Infrastructure\Extraction\OpenAiProposalReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class AiResourceLimitsTest extends TestCase
{
    public function testRequestBoundsAndSuccessfulCleanup(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $options = [];
        $response = new MockResponse(self::valid());
        $client = new RecordingAiHttpClient(static function (string $method, string $url, array $request) use (&$options, $response): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/chat/completions', $url);
            $options = $request;

            return $response;
        });
        $extractor = new OpenAiDocumentExtractor($client, new OpenAiProposalReader(), 'synthetic-not-a-key', 'gpt-4o-mini', $locks);
        $proposal = $extractor->propose('application/pdf', '%PDF-synthetic');
        $body = json_decode($options['body'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1024, $body['max_completion_tokens']);
        self::assertSame(1, $body['n']);
        self::assertFalse($body['store']);
        self::assertSame('gpt-4o-mini', $body['model']);
        self::assertSame(10.0, (float) $options['timeout']);
        self::assertSame(30.0, (float) $options['max_duration']);
        self::assertSame(0, $options['max_redirects']);
        self::assertFalse($options['buffer']);
        self::assertStringNotContainsString('synthetic-not-a-key', $options['body']);
        self::assertSame(12100, $proposal->details()->totalCents());
        self::assertTrue($client->issued?->getInfo('canceled'));
        $slot = $locks->createLock('aselto-ai-extraction-0');
        self::assertTrue($slot->acquire());
        $slot->release();
    }

    public function testExcessiveInputNeverCallsTheProvider(): void
    {
        $client = new RecordingAiHttpClient([]);
        $extractor = self::extractor($client);
        try {
            $extractor->propose('image/png', str_repeat('a', 8_000_001));
            self::fail('Oversize input accepted.');
        } catch (ExtractionFailed) {
            self::assertSame(0, $client->getRequestsCount());
        }
    }

    public function testMaximumInputDoesNotDoubleItsBase64Slashes(): void
    {
        $client = new RecordingAiHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertLessThanOrEqual(11_000_000, strlen($options['body']));

            return new MockResponse(self::valid());
        });
        self::extractor($client)->propose('image/jpeg', str_repeat("\xFF", 8_000_000));
        self::assertSame(1, $client->getRequestsCount());
    }

    public function testNoThirdConcurrentCallAndNoWaitingForASlot(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $first = $locks->createLock('aselto-ai-extraction-0');
        $second = $locks->createLock('aselto-ai-extraction-1');
        self::assertTrue($first->acquire());
        self::assertTrue($second->acquire());
        $client = new RecordingAiHttpClient(new MockResponse(self::valid()));
        $extractor = new OpenAiDocumentExtractor($client, new OpenAiProposalReader(), 'synthetic', 'gpt-4o-mini', $locks);
        try {
            $extractor->propose('image/png', 'synthetic');
            self::fail('Busy extractor called the provider.');
        } catch (ExtractionFailed) {
            self::assertSame(0, $client->getRequestsCount());
        }
        $second->release();
        $extractor->propose('image/png', 'synthetic');
        self::assertSame(1, $client->getRequestsCount());
        self::assertTrue($second->acquire());
        $first->release();
        $second->release();
    }

    #[DataProvider('invalidResponses')]
    public function testProviderFailuresAreCancelledWithoutRetryOrLeakingData(string $body, int $status = 200): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $response = new MockResponse($body, ['http_code' => $status]);
        $client = new RecordingAiHttpClient($response);
        $extractor = new OpenAiDocumentExtractor($client, new OpenAiProposalReader(), 'private-synthetic-secret', 'gpt-4o-mini', $locks);
        try {
            $extractor->propose('image/png', 'synthetic-document');
            self::fail('Invalid provider result accepted.');
        } catch (ExtractionFailed $exception) {
            self::assertSame('The document could not be read.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame(1, $client->getRequestsCount());
        self::assertTrue($client->issued?->getInfo('canceled'));
        $slot = $locks->createLock('aselto-ai-extraction-0');
        self::assertTrue($slot->acquire());
        $slot->release();
    }

    public function testChunkLimitStopsReadingTheRemainingBody(): void
    {
        $readTail = false;
        $body = static function () use (&$readTail): \Generator {
            yield str_repeat(' ', 32_768);
            yield str_repeat(' ', 32_768);
            yield 'x';
            $readTail = true;
            yield str_repeat('x', 100_000);
        };
        $response = new MockResponse($body());
        $client = new RecordingAiHttpClient($response);
        try {
            self::extractor($client)->propose('image/png', 'synthetic');
            self::fail('Oversize response accepted.');
        } catch (ExtractionFailed) {
            self::assertFalse($readTail);
        }
        self::assertTrue($client->issued?->getInfo('canceled'));
    }

    public function testSimulatedIdleTimeoutReleasesTheSlot(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $body = static function (): \Generator { yield ''; };
        $response = new MockResponse($body());
        $client = new RecordingAiHttpClient($response);
        $extractor = new OpenAiDocumentExtractor($client, new OpenAiProposalReader(), 'synthetic', 'gpt-4o-mini', $locks);
        try {
            $extractor->propose('image/png', 'synthetic');
            self::fail('Timeout accepted.');
        } catch (ExtractionFailed) {
            self::assertTrue($client->issued?->getInfo('canceled'));
        }
        $slot = $locks->createLock('aselto-ai-extraction-0');
        self::assertTrue($slot->acquire());
        $slot->release();
    }

    public function testSimulatedTotalDurationExcessFailsWithoutRetry(): void
    {
        $client = new RecordingAiHttpClient(new MockResponse(self::valid(), ['total_time' => 31]));
        try {
            self::extractor($client)->propose('image/png', 'synthetic');
            self::fail('Total duration excess accepted.');
        } catch (ExtractionFailed) {
            self::assertSame(1, $client->getRequestsCount());
        }
    }

    public function testOversizeDeclaredResponseDoesNotReadItsBody(): void
    {
        $readBody = false;
        $body = static function () use (&$readBody): \Generator {
            $readBody = true;
            yield self::valid();
        };
        $client = new RecordingAiHttpClient(new MockResponse($body(), ['response_headers' => ['content-length: 70000']]));
        try {
            self::extractor($client)->propose('image/png', 'synthetic');
            self::fail('Oversize declared body accepted.');
        } catch (ExtractionFailed) {
            self::assertFalse($readBody);
        }
        self::assertTrue($client->issued?->getInfo('canceled'));
    }

    public function testUnavailableLockStoreFailsBeforeHttp(): void
    {
        $store = $this->createStub(\Symfony\Component\Lock\PersistingStoreInterface::class);
        $store->method('save')->willThrowException(new \RuntimeException('synthetic-store-error'));
        $client = new RecordingAiHttpClient([]);
        $extractor = new OpenAiDocumentExtractor($client, new OpenAiProposalReader(), 'synthetic', 'gpt-4o-mini', new LockFactory($store));
        try {
            $extractor->propose('image/png', 'synthetic');
            self::fail('Unavailable lock store accepted.');
        } catch (ExtractionFailed $exception) {
            self::assertStringNotContainsString('synthetic-store-error', $exception->getMessage());
        }
        self::assertSame(0, $client->getRequestsCount());
    }

    public function testProposalReaderBoundsSizeBeforeTrimming(): void
    {
        $this->expectException(ExtractionFailed::class);
        (new OpenAiProposalReader())->read(str_repeat(' ', 16_385).'{"base":"100"}');
    }

    /** @return iterable<string, array{string, int}> */
    public static function invalidResponses(): iterable
    {
        yield 'server error' => ['private-error-body', 500];
        yield 'provider budget' => ['private-error-body', 429];
        yield 'redirect' => ['', 302];
        yield 'HTML' => ['<html>private-error</html>', 200];
        yield 'malformed JSON' => ['{', 200];
        yield 'excessive envelope' => [str_repeat(' ', 65_537), 200];
        yield 'excessive proposal' => [json_encode(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => str_repeat('x', 16_385)]]]], JSON_THROW_ON_ERROR), 200];
        foreach (['length', 'content_filter', 'tool_calls', null] as $reason) {
            yield 'finish '.($reason ?? 'missing') => [json_encode(['choices' => [['finish_reason' => $reason, 'message' => ['content' => '{"base":"100"}']]]], JSON_THROW_ON_ERROR), 200];
        }
        yield 'refusal' => [json_encode(['choices' => [['finish_reason' => 'stop', 'message' => ['refusal' => 'No', 'content' => '{"base":"100"}']]]], JSON_THROW_ON_ERROR), 200];
        yield 'multiple candidates' => ['{"choices":[{},{}]}', 200];
        yield 'non-string proposal' => ['{"choices":[{"finish_reason":"stop","message":{"content":123}}]}', 200];
    }

    private static function valid(): string
    {
        return json_encode(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => '{"direction":"expense","base":"100","tax_rate":21}']]]], JSON_THROW_ON_ERROR);
    }

    private static function extractor(MockHttpClient $client): OpenAiDocumentExtractor
    {
        return new OpenAiDocumentExtractor($client, new OpenAiProposalReader(), 'synthetic', 'gpt-4o-mini', new LockFactory(new InMemoryStore()));
    }
}

final class RecordingAiHttpClient extends MockHttpClient
{
    public ?\Symfony\Contracts\HttpClient\ResponseInterface $issued = null;

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $this->issued = parent::request($method, $url, $options);
    }
}
