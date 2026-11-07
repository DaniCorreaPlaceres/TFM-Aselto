<?php

namespace App\Documento\Domain;

enum DocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Extracting = 'extracting';
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Validated = 'validated';
    case Rejected = 'rejected';
}
