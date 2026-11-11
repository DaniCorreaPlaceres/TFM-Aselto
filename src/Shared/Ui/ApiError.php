<?php

namespace App\Shared\Ui;

use Symfony\Component\HttpFoundation\JsonResponse;

final class ApiError
{
    /** @param array<string, string|string[]> $headers */
    public static function response(int $status, string $code, string $message, array $headers = []): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status, ['Cache-Control' => 'private, no-store', ...$headers]);
    }
}
