<?php

namespace App\Documento\Infrastructure\Extraction;

use App\Documento\Domain\DocumentExtractor;
use App\Documento\Domain\Proposal;
use App\Documento\Domain\Tax;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ConfiguredDocumentExtractor implements DocumentExtractor
{
    public function __construct(
        private readonly SampleDocumentExtractor $sample,
        private readonly OpenAiDocumentExtractor $openAi,
        #[Autowire('%env(OPENAI_API_KEY)%')]
        private readonly string $apiKey,
    ) {
    }

    public function propose(string $mediaType, string $contents, Tax $tax = Tax::Vat, bool $allowsWithholding = false): Proposal
    {
        if ('' === trim($this->apiKey)) {
            return $this->sample->propose($mediaType, $contents, $tax, $allowsWithholding);
        }

        return $this->openAi->propose($mediaType, $contents, $tax, $allowsWithholding);
    }
}
