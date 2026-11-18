<?php

namespace App\Documento\Domain;

final class Proposal
{
    public function __construct(
        private readonly DocumentDetails $details,
        private readonly ProposalOrigin $origin,
    ) {
    }

    public function details(): DocumentDetails
    {
        return $this->details;
    }

    public function origin(): ProposalOrigin
    {
        return $this->origin;
    }
}
