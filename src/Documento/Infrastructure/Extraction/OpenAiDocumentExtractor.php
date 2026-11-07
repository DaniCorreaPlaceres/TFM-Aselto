<?php

namespace App\Documento\Infrastructure\Extraction;

use App\Documento\Domain\DocumentExtractor;
use App\Documento\Domain\ExtractionFailed;
use App\Documento\Domain\Proposal;
use App\Documento\Domain\ProposalOrigin;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenAiDocumentExtractor implements DocumentExtractor
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly OpenAiProposalReader $reader,
        #[Autowire('%env(OPENAI_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(OPENAI_MODEL)%')]
        private readonly string $model,
    ) {
    }

    public function propose(string $mediaType, string $contents): Proposal
    {
        if ('' === trim($this->apiKey)) {
            throw new ExtractionFailed('The extractor is not configured.');
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                ],
                'json' => [
                    'model' => $this->model,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => self::instructions()],
                            $this->file($mediaType, $contents),
                        ],
                    ]],
                ],
                'timeout' => 30,
            ]);
            $content = $response->toArray()['choices'][0]['message']['content'] ?? '';
        } catch (\Throwable) {
            throw new ExtractionFailed('The document could not be read.');
        }

        return new Proposal($this->reader->read(is_string($content) ? $content : ''), ProposalOrigin::Extracted);
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

    private static function instructions(): string
    {
        return 'Read this Spanish invoice or receipt. Reply with JSON only, using these keys: '
            .'direction (income, expense or null), issued_on (YYYY-MM-DD or null), party_name, tax_id, '
            .'base as a decimal string, and vat_rate as 0, 4, 10, 21 or null. '
            .'Use expense when the document is a purchase and income when the client issued it. '
            .'Do not invent missing fields.';
    }
}
