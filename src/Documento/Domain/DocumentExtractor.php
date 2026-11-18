<?php

namespace App\Documento\Domain;

interface DocumentExtractor
{
    /**
     * @throws ExtractionFailed
     */
    public function propose(string $mediaType, string $contents, Tax $tax = Tax::Vat, bool $allowsWithholding = false): Proposal;
}
