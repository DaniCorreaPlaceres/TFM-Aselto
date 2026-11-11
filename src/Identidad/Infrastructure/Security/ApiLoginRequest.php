<?php

namespace App\Identidad\Infrastructure\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 16)]
final class ApiLoginRequest
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ('/api/v1/auth/login' !== $request->getPathInfo() || !$request->isMethod('POST')) {
            return;
        }
        if ('json' !== $request->getContentTypeFormat()) {
            throw new UnsupportedMediaTypeHttpException();
        }
        if (strlen($request->getContent()) > 16_384) {
            throw new HttpException(413);
        }
        try {
            $data = json_decode($request->getContent(), false, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException();
        }
        if (!$data instanceof \stdClass || !isset($data->email, $data->password) || !is_string($data->email) || !is_string($data->password) || '' === $data->password || strlen($data->password) > 4096 || strlen(trim($data->email)) > 254 || false === filter_var(trim($data->email), FILTER_VALIDATE_EMAIL) || [] !== array_diff(array_keys(get_object_vars($data)), ['email', 'password'])) {
            throw new BadRequestHttpException();
        }
    }
}
