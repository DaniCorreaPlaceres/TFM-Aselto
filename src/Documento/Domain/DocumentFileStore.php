<?php

namespace App\Documento\Domain;

interface DocumentFileStore
{
    public function write(string $documentId, string $extension, string $contents): string;

    public function read(string $relativePath): string;
}
