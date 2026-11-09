<?php

namespace App\Comunicacion\Domain;

interface AttachmentStore
{
    public function write(string $id, string $extension, string $contents): string;

    public function read(string $path): string;

    public function remove(string $path): void;
}
