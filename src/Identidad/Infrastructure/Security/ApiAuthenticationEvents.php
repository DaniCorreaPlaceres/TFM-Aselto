<?php

namespace App\Identidad\Infrastructure\Security;

use App\Shared\Ui\ApiError;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTFailureEventInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\ExpiredTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\MissingTokenException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;

final class ApiAuthenticationEvents
{
    public function __construct(private readonly RequestStack $requests, #[Autowire('%lexik_jwt_authentication.token_ttl%')] private readonly int $ttl)
    {
    }

    #[AsEventListener(event: Events::AUTHENTICATION_SUCCESS)]
    public function success(AuthenticationSuccessEvent $event): void
    {
        if (!$this->isApi()) {
            return;
        }
        $event->setData(['token' => $event->getData()['token'], 'token_type' => 'Bearer', 'expires_in' => $this->ttl]);
        $event->getResponse()->headers->set('Cache-Control', 'private, no-store');
    }

    #[AsEventListener(event: Events::AUTHENTICATION_FAILURE)]
    public function failure(AuthenticationFailureEvent $event): void
    {
        if ($this->isApi()) {
            $event->setResponse(ApiError::response(401, 'invalid_credentials', 'El correo o la contraseña no son correctos.'));
        }
    }

    #[AsEventListener(event: Events::JWT_NOT_FOUND)]
    #[AsEventListener(event: Events::JWT_INVALID)]
    #[AsEventListener(event: Events::JWT_EXPIRED)]
    public function tokenFailure(JWTFailureEventInterface $event): void
    {
        if (!$this->isApi()) {
            return;
        }
        $code = match (true) {
            $event->getException() instanceof MissingTokenException => 'token_missing', $event->getException() instanceof ExpiredTokenException => 'token_expired', default => 'token_invalid',
        };
        $message = match ($code) {
            'token_missing' => 'Envía tu token en la cabecera Authorization: Bearer.', 'token_expired' => 'Tu acceso ha caducado. Vuelve a entrar.', default => 'El token no es válido. Vuelve a entrar.',
        };
        $event->setResponse(ApiError::response(401, $code, $message, ['WWW-Authenticate' => 'Bearer']));
    }

    private function isApi(): bool
    {
        return 1 === preg_match('#^/api(?:/|$)#', $this->requests->getCurrentRequest()?->getPathInfo() ?? '');
    }
}
