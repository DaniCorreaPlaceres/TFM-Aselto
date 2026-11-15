<?php

namespace App\Shared\Ui;

use App\Shared\Domain\RecordCriteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class RecordTableInput
{
    public function read(Request $request): RecordCriteria
    {
        try {
            return new RecordCriteria($this->number($request, 'start', 0), $this->number($request, 'length', 25), $request->query->getString('q'), $this->number($request, 'column', 0), $request->query->getString('order', 'asc'), $request->query->getString('filter'), $request->query->getString('section'), $request->query->getString('owner'));
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException('Los filtros o el tamaño de página no son válidos.', $exception);
        }
    }

    public function number(Request $request, string $name, int $default): int
    {
        $value = $request->query->getString($name, (string) $default);
        if (!ctype_digit($value) || strlen($value) > 7) {
            throw new BadRequestHttpException();
        }

        return (int) $value;
    }
}
