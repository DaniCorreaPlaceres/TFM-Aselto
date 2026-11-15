<?php

namespace App\Identidad\Infrastructure\Security;

use App\Shared\Ui\ApiError;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

// After API shape validation (16), before authentication (8).
#[AsEventListener(event: KernelEvents::REQUEST, priority: 12)]
final class LoginRateLimitListener
{
    public function __construct(private readonly AuthenticationRateLimits $limits, private readonly Environment $twig)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = rawurldecode($request->getPathInfo());
        $api = '/api/v1/auth/login' === $path;
        if (!$event->isMainRequest() || !$request->isMethod('POST') || (!$api && '/entrar' !== $path)) {
            return;
        }
        $email = $api ? $request->toArray()['email'] : $request->request->get('email', '');
        $retry = $this->limits->login($request->getClientIp() ?? 'unknown', is_string($email) ? $email : '');
        if (0 === $retry) {
            return;
        }
        $message = 'Has hecho demasiados intentos. Espera unos minutos antes de volver a entrar.';
        $headers = ['Retry-After' => (string) $retry, 'Cache-Control' => 'private, no-store'];
        $event->setResponse($api
            ? ApiError::response(429, 'too_many_attempts', $message, $headers)
            : new Response($this->twig->render('identity/login.html.twig', ['lastEmail' => $email, 'errorMessage' => $message]), 429, $headers));
    }
}
