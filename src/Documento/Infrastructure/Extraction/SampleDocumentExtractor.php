<?php

namespace App\Documento\Infrastructure\Extraction;

use App\Documento\Application\DocumentForm;
use App\Documento\Domain\DocumentExtractor;
use App\Documento\Domain\Proposal;
use App\Documento\Domain\ProposalOrigin;

final class SampleDocumentExtractor implements DocumentExtractor
{
    public function propose(string $mediaType, string $contents): Proposal
    {
        return new Proposal(
            DocumentForm::read('gasto', '2026-03-12', 'Proveedor de ejemplo', '00000000T', '100,00', '21'),
            ProposalOrigin::Sample,
        );
    }
}
