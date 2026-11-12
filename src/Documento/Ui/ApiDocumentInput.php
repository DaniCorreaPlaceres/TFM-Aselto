<?php

namespace App\Documento\Ui;

use App\Documento\Application\DocumentForm;
use App\Documento\Domain\DocumentDetails;
use App\Documento\Domain\Tax;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

final class ApiDocumentInput
{
    public const FIELDS = ['version', 'direction', 'issued_on', 'party_name', 'tax_id', 'base', 'tax_rate', 'surcharge_rate', 'withholding_rate'];

    /**
     * @param list<string> $allowed
     *
     * @return array<string, mixed>
     */
    public function json(Request $request, array $allowed): array
    {
        if ('json' !== $request->getContentTypeFormat()) {
            throw new UnsupportedMediaTypeHttpException();
        }
        if (strlen($request->getContent()) > 32_768) {
            throw new HttpException(413);
        }
        try {
            $object = json_decode($request->getContent(), false, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException();
        }
        if (!$object instanceof \stdClass || [] !== array_diff(array_keys(get_object_vars($object)), $allowed)) {
            throw new BadRequestHttpException();
        }
        $data = get_object_vars($object);
        $this->version($data);
        foreach ($data as $key => $value) {
            if ('version' !== $key && null !== $value && !is_string($value)) {
                throw new BadRequestHttpException();
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    public function version(array $data): int
    {
        $version = $data['version'] ?? null;
        if (!is_int($version) || $version < 1 || $version > 2_147_483_647) {
            throw new BadRequestHttpException();
        }

        return $version;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{string, string, string, string, string, string, string, string}
     */
    public function arguments(array $data): array
    {
        $direction = match ($data['direction'] ?? null) {
            null, '' => '', 'income' => 'ingreso', 'expense' => 'gasto', default => throw new UnprocessableEntityHttpException(),
        };
        foreach (['base', 'tax_rate', 'surcharge_rate', 'withholding_rate'] as $field) {
            $value = $data[$field] ?? '';
            if ('' !== $value && 1 !== preg_match('/\A\d{1,9}(?:\.\d{1,2})?\z/', $value)) {
                throw new UnprocessableEntityHttpException();
            }
        }

        return [$direction, $data['issued_on'] ?? '', $data['party_name'] ?? '', $data['tax_id'] ?? '', $data['base'] ?? '', $data['tax_rate'] ?? '', $data['surcharge_rate'] ?? '', $data['withholding_rate'] ?? ''];
    }

    /** @param array<string, mixed> $data */
    public function details(array $data, Tax $tax): DocumentDetails
    {
        [$direction, $date, $party, $taxId, $base, $rate, $surcharge, $withholding] = $this->arguments($data);

        return DocumentForm::read($direction, $date, $party, $taxId, $base, $rate, $tax, $surcharge, $withholding);
    }

    /** @return array{name: string, media_type: string, contents: string, version: ?int} */
    public function upload(Request $request, bool $supporting = false): array
    {
        if ('multipart/form-data' !== strtolower(trim(explode(';', (string) $request->headers->get('Content-Type'))[0]))) {
            throw new UnsupportedMediaTypeHttpException();
        }
        if ([] !== array_diff(array_keys($request->request->all()), $supporting ? ['version'] : []) || [] !== array_diff(array_keys($request->files->all()), ['file'])) {
            throw new BadRequestHttpException();
        }
        $version = null;
        if ($supporting) {
            $value = $request->request->get('version');
            if (!is_string($value) || 1 !== preg_match('/\A[1-9]\d{0,9}\z/', $value)) {
                throw new BadRequestHttpException();
            }
            $version = $this->version(['version' => (int) $value]);
        }
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new UnprocessableEntityHttpException();
        }
        if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new HttpException(413);
        }
        if (!$file->isValid()) {
            throw new UnprocessableEntityHttpException();
        }
        if ($file->getSize() > 8_000_000) {
            throw new HttpException(413);
        }
        $contents = file_get_contents($file->getPathname());
        if (false === $contents) {
            throw new UnprocessableEntityHttpException();
        }

        return ['name' => $file->getClientOriginalName(), 'media_type' => (string) $file->getMimeType(), 'contents' => $contents, 'version' => $version];
    }
}
