<?php

namespace App\Documento\Domain;

interface DocumentExtractor
{
    /**
     * @throws ExtractionFailed
     */
    public function propose(string $mediaType, string $contents): Proposal;
}
