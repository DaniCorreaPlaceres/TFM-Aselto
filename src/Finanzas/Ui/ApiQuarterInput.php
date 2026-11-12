<?php

namespace App\Finanzas\Ui;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

final class ApiQuarterInput
{
    public function scope(Request $request): ?string
    {
        $query = $request->query->all();
        $scope = $query['scope'] ?? null;
        if ([] !== array_diff(array_keys($query), ['scope']) || (null !== $scope && (!is_string($scope) || !preg_match('/\A(?:personal|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\z/', $scope)))) {
            throw new BadRequestHttpException();
        }

        return $scope;
    }

    /** @return array{version: int, fingerprint: string} */
    public function mutation(Request $request, bool $close): array
    {
        if ('json' !== $request->getContentTypeFormat()) {
            throw new UnsupportedMediaTypeHttpException();
        }
        if (strlen($request->getContent()) > 4096) {
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
        $version = $data['version'] ?? null;
        $fingerprint = $data['fingerprint'] ?? '';
        if ([] !== array_diff(array_keys($data), $close ? ['version', 'fingerprint'] : ['version']) || !is_int($version) || $version < 0 || $version > 2_147_483_647 || !is_string($fingerprint) || ($close && !preg_match('/\A[0-9a-f]{64}\z/', $fingerprint))) {
            throw new BadRequestHttpException();
        }

        return ['version' => $version, 'fingerprint' => $fingerprint];
    }
}
