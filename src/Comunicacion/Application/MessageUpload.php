<?php

namespace App\Comunicacion\Application;

use App\Comunicacion\Domain\InvalidMessage;

final class MessageUpload
{
    /** @return array{name: string, type: string, extension: string} */
    public static function read(string $name, string $contents): array
    {
        if ('' === $contents || strlen($contents) > 8_000_000) {
            throw new InvalidMessage('El archivo está vacío o supera los 8 MB.');
        }
        $type = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        $extensions = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!is_string($type) || !isset($extensions[$type]) || ('application/pdf' === $type ? !str_starts_with($contents, '%PDF-') : false === @getimagesizefromstring($contents))) {
            throw new InvalidMessage('El archivo debe ser un PDF o una imagen JPEG, PNG o WebP válida.');
        }
        $name = trim(preg_replace('/[\x00-\x1F\x7F]/', '', basename(str_replace('\\', '/', $name))) ?? '');

        return ['name' => '' === $name ? 'adjunto.'.$extensions[$type] : mb_substr($name, 0, 180), 'type' => $type, 'extension' => $extensions[$type]];
    }
}
