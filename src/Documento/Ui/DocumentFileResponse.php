<?php

namespace App\Documento\Ui;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

final class DocumentFileResponse
{
    public static function create(string $contents, string $mediaType, string $name): Response
    {
        $fallback = preg_replace('/[^\x20-\x7e]/', '_', str_replace('%', '_', $name)) ?: 'documento';

        return new Response($contents, 200, [
            'Content-Type' => $mediaType,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $name, $fallback),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
