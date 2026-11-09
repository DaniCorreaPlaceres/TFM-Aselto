<?php

namespace App\Documento\Domain;

enum ReviewAction: string
{
    case Amended = 'amended';
    case Submitted = 'submitted';
    case Validated = 'validated';
    case Rejected = 'rejected';
    case Requested = 'requested';
    case Attached = 'attached';
}
