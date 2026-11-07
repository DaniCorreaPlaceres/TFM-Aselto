<?php

namespace App\Documento\Domain;

enum ProposalOrigin: string
{
    case Extracted = 'extracted';
    case Sample = 'sample';
}
