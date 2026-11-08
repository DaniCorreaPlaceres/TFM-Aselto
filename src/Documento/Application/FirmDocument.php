<?php

namespace App\Documento\Application;

use App\Documento\Domain\Document;

final class FirmDocument
{
    public function __construct(public readonly Document $document, public readonly string $clientName)
    {
    }
}
