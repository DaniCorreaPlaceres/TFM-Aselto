<?php

namespace App\Finanzas\Infrastructure\Realtime;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LogoutEvent::class, priority: -128)]
final class ClearQuarterSubscription
{
    public function __construct(private readonly HubInterface $hub)
    {
    }

    public function __invoke(LogoutEvent $event): void
    {
        $response = $event->getResponse() ?? new RedirectResponse('/entrar');
        $response->headers->clearCookie($this->hub->getCookieName(), '/.well-known/mercure', secure: $event->getRequest()->isSecure(), httpOnly: true, sameSite: Cookie::SAMESITE_STRICT);
        $event->setResponse($response);
    }
}
