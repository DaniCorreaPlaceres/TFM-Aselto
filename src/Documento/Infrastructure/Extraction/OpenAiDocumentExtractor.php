<?php

namespace App\Documento\Infrastructure\Extraction;

use App\Documento\Domain\DocumentExtractor;
use App\Documento\Domain\ExtractionFailed;
use App\Documento\Domain\Proposal;
use App\Documento\Domain\ProposalOrigin;
use App\Documento\Domain\Tax;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenAiDocumentExtractor implements DocumentExtractor
{
    public const MAX_INPUT_BYTES = 8_000_000;
    public const MAX_REQUEST_BYTES = 11_000_000;
    public const MAX_RESPONSE_BYTES = 65_536;
    public const MAX_PROPOSAL_BYTES = 16_384;

    public function __construct(
        #[Autowire(service: 'ai_extraction.client')]
        private readonly HttpClientInterface $httpClient,
        private readonly OpenAiProposalReader $reader,
        #[Autowire('%env(OPENAI_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(OPENAI_MODEL)%')]
        private readonly string $model,
        private readonly LockFactory $locks,
        #[Autowire('%app.ai_max_concurrent%')]
        private readonly int $maxConcurrent = 2,
        #[Autowire('%app.ai_max_completion_tokens%')]
        private readonly int $maxCompletionTokens = 1024,
    ) {
        if ($maxConcurrent < 1 || $maxConcurrent > 8 || $maxCompletionTokens < 1 || $maxCompletionTokens > 4096) {
            throw new \InvalidArgumentException('Invalid AI resource limits.');
        }
    }

    public function propose(string $mediaType, string $contents, Tax $tax = Tax::Vat, bool $allowsWithholding = false): Proposal
    {
        if ('' === trim($this->apiKey) || '' === $contents || strlen($contents) > self::MAX_INPUT_BYTES || !in_array($mediaType, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new ExtractionFailed('The extractor is not configured.');
        }

        $lock = $this->slot();
        $response = null;
        try {
            $body = json_encode([
                'model' => $this->model,
                'max_completion_tokens' => $this->maxCompletionTokens,
                'n' => 1,
                'store' => false,
                'response_format' => ['type' => 'json_object'],
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => self::instructions($tax, $allowsWithholding)],
                        $this->file($mediaType, $contents),
                    ],
                ]],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            if (strlen($body) > self::MAX_REQUEST_BYTES) {
                throw new ExtractionFailed('The request is too large.');
            }
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => ['Authorization' => 'Bearer '.$this->apiKey, 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
                'body' => $body,
                'timeout' => 10,
                'max_duration' => 30,
                'max_redirects' => 0,
                'buffer' => false,
                'on_progress' => static function (int $downloaded, int $size, array $info): void {
                    if ($downloaded > self::MAX_RESPONSE_BYTES || $size > self::MAX_RESPONSE_BYTES || ($info['total_time'] ?? 0) > 30) {
                        throw new ExtractionFailed('The provider exceeded its response budget.');
                    }
                },
            ]);
            if (200 !== $response->getStatusCode()) {
                throw new ExtractionFailed('The provider rejected the request.');
            }
            $length = $response->getHeaders(false)['content-length'][0] ?? '';
            if (ctype_digit($length) && (float) $length > self::MAX_RESPONSE_BYTES) {
                throw new ExtractionFailed('The response is too large.');
            }
            $payload = '';
            foreach ($this->httpClient->stream($response, 10) as $chunk) {
                if ($chunk->isTimeout()) {
                    throw new ExtractionFailed('The provider timed out.');
                }
                $data = $chunk->getContent();
                if (strlen($payload) + strlen($data) > self::MAX_RESPONSE_BYTES) {
                    throw new ExtractionFailed('The response is too large.');
                }
                $payload .= $data;
            }
            $data = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);
            $choices = is_array($data) ? ($data['choices'] ?? null) : null;
            if (!is_array($choices) || 1 !== count($choices) || 'stop' !== ($choices[0]['finish_reason'] ?? null) || !empty($choices[0]['message']['refusal']) || !empty($choices[0]['message']['tool_calls'])) {
                throw new ExtractionFailed('The provider did not complete a proposal.');
            }
            $content = $choices[0]['message']['content'] ?? null;
            if (!is_string($content) || strlen($content) > self::MAX_PROPOSAL_BYTES) {
                throw new ExtractionFailed('Invalid proposal size.');
            }

            return new Proposal($this->reader->read($content, $tax, $allowsWithholding), ProposalOrigin::Extracted);
        } catch (\Throwable) {
            throw new ExtractionFailed('The document could not be read.');
        } finally {
            try {
                $response?->cancel();
            } catch (\Throwable) {
                // Cancellation must not hide the safe manual-review fallback.
            } finally {
                try {
                    $lock->release();
                } catch (\Throwable) {
                    throw new ExtractionFailed('The extractor could not release its slot.');
                }
            }
        }
    }

    private function slot(): LockInterface
    {
        try {
            for ($index = 0; $index < $this->maxConcurrent; ++$index) {
                $lock = $this->locks->createLock('aselto-ai-extraction-'.$index, 45);
                if ($lock->acquire()) {
                    return $lock;
                }
            }
        } catch (\Throwable) {
            throw new ExtractionFailed('The extractor is temporarily unavailable.');
        }
        throw new ExtractionFailed('The extractor is temporarily busy.');
    }

    /**
     * @return array<string, mixed>
     */
    private function file(string $mediaType, string $contents): array
    {
        $encoded = base64_encode($contents);

        if ('application/pdf' === $mediaType) {
            return [
                'type' => 'file',
                'file' => [
                    'filename' => 'documento.pdf',
                    'file_data' => 'data:application/pdf;base64,'.$encoded,
                ],
            ];
        }

        return [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'data:'.$mediaType.';base64,'.$encoded,
            ],
        ];
    }

    private static function instructions(Tax $tax, bool $allowsWithholding): string
    {
        $rateInstructions = match ($tax) {
            Tax::Vat => 'The client territory uses IVA. tax_rate must be 0, 4, 10, 21 or null.',
            Tax::Igic => 'The client territory uses IGIC. tax_rate must be 0, 3, 7, 9.5, 15, 20 or null.',
            Tax::Ipsi => 'The client territory uses IPSI. Always return null for tax_rate; the person enters it manually.',
        };

        return 'Read this Spanish invoice or receipt. Reply with JSON only, using these keys: '
            .'direction (income, expense or null), issued_on (YYYY-MM-DD or null), party_name, tax_id, '
            .'base as a decimal string, tax_rate, surcharge_rate, and withholding_rate. '.$rateInstructions.' '
            .'surcharge_rate is null unless an equivalence surcharge is explicitly printed on an expense with IVA. '
            .'Allowed surcharge rates are 5.2 with IVA 21, 1.4 with IVA 10, 0.5 with IVA 4, or 1.75 for tobacco with IVA 21. '
            .'Never infer a surcharge from the IVA rate alone. For IGIC, IPSI or income return null for surcharge_rate. '
            .($allowsWithholding
                ? 'The client is self-employed. withholding_rate is the IRPF percentage explicitly printed on income, or null. Do not infer it from the client profile or tax rate. '
                : 'The client cannot use income withholding. Always return null for withholding_rate. ')
            .'For expenses always return null for withholding_rate. Never return calculated quotas or totals. '
            .'Use expense when the document is a purchase and income when the client issued it. '
            .'Do not invent missing fields.';
    }
}
