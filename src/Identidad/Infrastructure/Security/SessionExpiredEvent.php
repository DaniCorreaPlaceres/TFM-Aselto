<?php

namespace App\Identidad\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SessionExpiredEvent
{
    public function __construct(public readonly Request $request, public readonly Response $response)
    {
    }
}
