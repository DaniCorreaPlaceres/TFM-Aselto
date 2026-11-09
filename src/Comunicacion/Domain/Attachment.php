<?php

namespace App\Comunicacion\Domain;

final class Attachment
{
    public function __construct(public readonly string $id, public readonly string $name, public readonly string $path, public readonly string $mediaType)
    {
    }
}
