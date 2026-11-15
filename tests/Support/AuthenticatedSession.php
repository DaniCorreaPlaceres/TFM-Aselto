<?php

namespace App\Tests\Support;

use App\Identidad\Application\SessionLifetime;
use App\Identidad\Infrastructure\Security\WebSessionLifetime;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class AuthenticatedSession
{
    public static function start(SessionInterface $session, ?int $now = null): void
    {
        $session->set(WebSessionLifetime::KEY, (new SessionLifetime())->start($now ?? time()));
    }
}
