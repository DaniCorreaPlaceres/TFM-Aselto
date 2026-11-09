<?php

namespace App\Documento\Application;

use App\Documento\Domain\UnsupportedFile;

final class DocumentUpload
{
    /** @return array{originalName: string, mediaType: string, extension: string} */
    public static function read(string $originalName, string $mediaType, string $contents): array
    {
        if ('' === $contents || strlen($contents) > 8_000_000) {
            throw new UnsupportedFile('The file is empty or too large.');
        }
        $extensions = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mediaType = mb_strtolower(trim($mediaType));
        if (!isset($extensions[$mediaType])) {
            throw new UnsupportedFile('The file type is not allowed.');
        }
        $name = trim(basename(str_replace('\\', '/', $originalName)));
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';
        $name = '' === $name ? 'documento' : mb_substr($name, 0, 180);

        return ['originalName' => $name, 'mediaType' => 'image/jpg' === $mediaType ? 'image/jpeg' : $mediaType, 'extension' => $extensions[$mediaType]];
    }
}
