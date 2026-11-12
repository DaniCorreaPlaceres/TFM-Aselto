<?php

namespace App\Shared\Ui;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -64)]
final class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!preg_match('#^/api(?:/|$)#', $event->getRequest()->getPathInfo())) {
            return;
        }
        $error = $event->getThrowable();
        $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 500;
        [$code, $message] = match ($status) {
            400 => ['invalid_request', 'La petición no es válida. Revisa el formato y los campos.'],
            401 => ['authentication_required', 'Debes autenticarte para continuar.'],
            403 => ['access_denied', 'No tienes permiso para esta operación.'],
            404 => ['not_found', 'No encontramos el recurso solicitado.'],
            405 => ['method_not_allowed', 'Este método no está permitido en esta ruta.'],
            413 => ['payload_too_large', 'El contenido de la petición es demasiado grande.'],
            415 => ['unsupported_media_type', 'El tipo de contenido no es compatible con esta operación.'],
            422 => ['validation_failed', 'Revisa los datos del registro y el archivo enviado.'],
            default => ['internal_error', 'No hemos podido completar la petición. Vuelve a intentarlo.'],
        };
        $event->setResponse(ApiError::response($status, $code, $message, $error instanceof HttpExceptionInterface ? $error->getHeaders() : []));
    }
}
