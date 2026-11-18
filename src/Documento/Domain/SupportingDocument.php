<?php

namespace App\Documento\Domain;

final class SupportingDocument
{
    public function __construct(
        public readonly string $id,
        public readonly string $originalName,
        public readonly string $storedPath,
        public readonly string $mediaType,
        public readonly \DateTimeImmutable $uploadedAt,
    ) {
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id)
            || '' === $originalName || mb_strlen($originalName) > 180
            || '' === $storedPath || str_contains($storedPath, '..')
            || !in_array($mediaType, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new InvalidDocument('The supporting document is not valid.');
        }
    }
}
