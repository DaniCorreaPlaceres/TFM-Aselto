<?php

// Read-only real-kernel checks; no mail, AI or database writes.
require dirname(__DIR__).'/bootstrap.php';

use App\Kernel;
use App\Shared\Infrastructure\Http\BrowserSecurityHeaders;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

$kernel = new Kernel('dev', true);
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
};
$http = static function (string $path, string $method = 'GET') use ($kernel): Response {
    $request = Request::create('http://localhost'.$path, $method);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
};
$common = static function (Response $response) use ($check): void {
    foreach (['X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'DENY', 'Referrer-Policy' => 'no-referrer', 'Permissions-Policy' => BrowserSecurityHeaders::PERMISSIONS] as $name => $value) {
        $check($value === $response->headers->get($name), $name.' must protect every response.');
    }
    $check(str_contains($response->headers->get('Content-Security-Policy', ''), "frame-ancestors 'none'"), 'Framing must be denied.');
    $check(!$response->headers->has('Strict-Transport-Security'), 'Local HTTP must not claim verified HTTPS.');
};
try {
    $nonces = [];
    foreach (['/', '/?_csp_nonce=attacker', '/entrar', '/api/docs'] as $path) {
        $response = $http($path);
        $check(200 === $response->getStatusCode(), 'Public HTML must remain available.');
        $common($response);
        $policy = $response->headers->get('Content-Security-Policy', '');
        $check(1 === preg_match("/script-src 'self' 'nonce-([A-Za-z0-9+\\/=]+)'/", $policy, $match), 'Scripts require a random nonce.');
        $nonces[] = $match[1];
        $check(32 === strlen($match[1]), 'Nonce must contain 192 random bits.');
        $check(!str_contains($policy, "'unsafe-eval'") && !str_contains($policy, "'wasm-unsafe-eval'"), 'Ordinary pages must not allow evaluation.');
        $check(str_contains($policy, "script-src-attr 'none'"), 'Inline event handlers must be blocked.');
        $check(str_contains($response->headers->get('Cache-Control', ''), 'no-store'), 'Nonce HTML must not be reused from cache.');
        $html = $response->getContent();
        preg_match_all('/<script\b([^>]*)>(.*?)<\/script>/s', $html, $scripts, PREG_SET_ORDER);
        foreach ($scripts as $script) {
            if (!str_contains($script[1], 'src=')) {
                $check(str_contains($script[1], 'nonce="'.$match[1].'"'), 'Every inline script must match its response nonce.');
            }
        }
        $check(!str_contains($html, 'data:application/javascript'), 'CSS must not require data script imports.');
        if ('/api/docs' !== $path) {
            $check(1 === preg_match('{<link rel="stylesheet" href="/assets/styles/app-[^" ]+\.css"}', $html), 'Compiled Sass must have an explicit local stylesheet.');
        }
    }
    $check(count($nonces) === count(array_unique($nonces)), 'Each response needs a distinct nonce.');
    foreach ([['/cuenta', 'GET', 302], ['/api/v1/me', 'GET', 401], ['/security-missing-page', 'GET', 404], ['/salir', 'GET', 405]] as [$path, $method, $status]) {
        $response = $http($path, $method);
        $check($status === $response->getStatusCode(), 'Authentication/error status must remain unchanged.');
        $common($response);
    }
    foreach (['activar', 'recuperar', 'invitacion', 'confirmar-correo-admin'] as $prefix) {
        foreach (['malformed', str_repeat('a', 64)] as $token) {
            $response = $http('/'.$prefix.'/'.$token);
            $common($response);
            $check(str_contains($response->headers->get('Cache-Control', ''), 'no-store'), 'Sensitive links, including errors, must not be cached.');
        }
    }
} finally {
    $kernel->shutdown();
}
echo 'Browser security headers integration checks: '.$checks." passed.\nNo fixtures or external services used.\n";
