<?php

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class BrowserSecurityHeaders
{
    public const NONCE = '_csp_nonce';
    public const PERMISSIONS = 'camera=(self), microphone=(), geolocation=(), payment=(), usb=(), display-capture=()';

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 256)]
    public function nonce(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $event->getRequest()->attributes->set(self::NONCE, base64_encode(random_bytes(24)));
        }
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -2048)]
    public function headers(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $response = $event->getResponse();
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', self::PERMISSIONS);
        $html = str_contains(strtolower($response->headers->get('Content-Type', '')), 'text/html');
        if ($html) {
            $nonce = $request->attributes->get(self::NONCE);
            if (!is_string($nonce)) {
                throw new \LogicException('Missing CSP nonce.');
            }
            $wasm = in_array($request->attributes->get('_route'), ['client_document', 'firm_document'], true) ? " 'wasm-unsafe-eval'" : '';
            $response->headers->set('Content-Security-Policy', "default-src 'self'; base-uri 'none'; object-src 'none'; frame-ancestors 'none'; frame-src 'none'; form-action 'self'; script-src 'self' 'nonce-$nonce'$wasm; script-src-attr 'none'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data: blob:; worker-src 'self'; connect-src 'self'");
            $response->headers->set('Cache-Control', 'private, no-store');
        } else {
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
        }
        if (preg_match('{^/(?:activar|recuperar|invitacion|confirmar-correo-admin)/}', rawurldecode($request->getPathInfo()))) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }
    }
}
