<?php

namespace App\Documento\Infrastructure\Extraction;

use App\Documento\Domain\DocumentExtractor;
use App\Documento\Domain\ExtractionFailed;
use App\Documento\Domain\Proposal;
use App\Documento\Domain\ProposalOrigin;
use App\Documento\Domain\Tax;
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

    public function propose(string $mediaType, string $contents, Tax $tax = Tax::Vat, bool $allowsWithholding = false): Proposal
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
                            ['type' => 'text', 'text' => self::instructions($tax, $allowsWithholding)],
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

        return new Proposal($this->reader->read(is_string($content) ? $content : '', $tax, $allowsWithholding), ProposalOrigin::Extracted);
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
