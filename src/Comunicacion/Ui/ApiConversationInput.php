<?php

namespace App\Comunicacion\Ui;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

final class ApiConversationInput
{
    public const UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    /** @return array<string, mixed> */
    public function data(Request $request, bool $start, bool $resolve = false): array
    {
        if ([] !== $request->query->all()) {
            throw new BadRequestHttpException();
        }
        $multipart = 'multipart/form-data' === strtolower(trim(explode(';', (string) $request->headers->get('Content-Type'))[0]));
        if ($multipart && !$resolve) {
            $data = $request->request->all();
        } elseif ('json' === $request->getContentTypeFormat()) {
            if (strlen($request->getContent()) > 32_768) {
                throw new HttpException(413);
            }
            try {
                $object = json_decode($request->getContent(), false, 4, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new BadRequestHttpException();
            }
            if (!$object instanceof \stdClass) {
                throw new BadRequestHttpException();
            }
            $data = get_object_vars($object);
        } else {
            throw new UnsupportedMediaTypeHttpException();
        }
        $allowed = $resolve ? [] : ($start ? ['owner_id', 'subject', 'text', 'request', 'document_id'] : ['text']);
        if ([] !== array_diff(array_keys($data), $allowed)) {
            throw new BadRequestHttpException();
        }
        foreach ($data as $field => $value) {
            if ('request' === $field) {
                if ($multipart && in_array($value, ['true', 'false'], true)) {
                    $data[$field] = 'true' === $value;
                } elseif (!is_bool($value)) {
                    throw new BadRequestHttpException();
                }
            } elseif (!is_string($value) && !('document_id' === $field && null === $value)) {
                throw new BadRequestHttpException();
            }
        }
        foreach ($resolve ? [] : ($start ? ['owner_id', 'subject', 'text'] : ['text']) as $required) {
            if (!isset($data[$required])) {
                throw new BadRequestHttpException();
            }
        }
        foreach (['owner_id', 'document_id'] as $field) {
            if (isset($data[$field]) && !preg_match('/\A'.self::UUID.'\z/', $data[$field])) {
                throw new BadRequestHttpException();
            }
        }

        return $data;
    }

    /** @return list<array{name: string, contents: string}> */
    public function uploads(Request $request): array
    {
        $all = $request->files->all();
        if ([] !== array_diff(array_keys($all), ['attachments'])) {
            throw new BadRequestHttpException();
        }
        $files = $all['attachments'] ?? [];
        if (!is_array($files) || !array_is_list($files)) {
            throw new BadRequestHttpException();
        }
        if (count($files) > 3) {
            throw new UnprocessableEntityHttpException('Puedes adjuntar hasta tres archivos por mensaje.');
        }
        $uploads = [];
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                throw new UnprocessableEntityHttpException('No hemos podido recibir el archivo adjunto.');
            }
            if ($file->getSize() > 8_000_000) {
                throw new HttpException(413);
            }
            $contents = file_get_contents($file->getPathname());
            if (false === $contents) {
                throw new UnprocessableEntityHttpException('No hemos podido leer el archivo adjunto.');
            }
            $uploads[] = ['name' => $file->getClientOriginalName(), 'contents' => $contents];
        }

        return $uploads;
    }
}
