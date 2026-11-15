<?php

namespace App\Identidad\Infrastructure\Security;

use App\Identidad\Application\SessionLifetime;
use App\Shared\Domain\Clock;
use App\Shared\Ui\ApiError;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\AccessMapInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class WebSessionLifetime
{
    public const KEY = '_auth_session_lifetime';
    private const CHECKED = '_auth_session_lifetime_checked';
    private const EXPIRED_PUBLIC = '_auth_session_expired_public';

    public function __construct(private readonly SessionLifetime $policy, private readonly Clock $clock, private readonly TokenStorageInterface $tokens, private readonly EventDispatcherInterface $events, private readonly AccessMapInterface $access)
    {
    }

    // After session/routing, before login middleware can render an authenticated layout.
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 18)]
    public function check(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = rawurldecode($request->getPathInfo());
        if (!$event->isMainRequest() || preg_match('{^/(?:api|assets|bundles|swagger-ui|_profiler|_wdt)(?:/|$)}', $path)
            || '/salud' === $path || !$request->hasPreviousSession()) {
            return;
        }
        $session = $request->getSession();
        if (!$session->has('_security_main')) {
            return;
        }
        $now = $this->clock->now()->getTimestamp();
        if (!$this->policy->expired($session->get(self::KEY), $now)) {
            $request->attributes->set(self::CHECKED, $now);

            return;
        }

        $session->invalidate();
        $this->tokens->setToken(null);
        $message = 'Tu sesión ha caducado. Vuelve a entrar.';
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('session_expired', $message);
        }
        [$roles] = $this->access->getPatterns($request);
        if ($request->isMethod('GET') && in_array('PUBLIC_ACCESS', $roles ?? [], true)) {
            // Keep public recovery/invitation links usable after discarding old authentication.
            $request->attributes->set(self::EXPIRED_PUBLIC, true);

            return;
        }
        $response = $this->automatic($request)
            ? ApiError::response(401, 'session_expired', $message)
            : new RedirectResponse('/entrar', $request->isMethod('GET') ? 302 : 303, ['Cache-Control' => 'private, no-store']);
        $this->events->dispatch(new SessionExpiredEvent($request, $response));
        $event->setResponse($response);
    }

    #[AsEventListener(event: LoginSuccessEvent::class, priority: -128)]
    public function login(LoginSuccessEvent $event): void
    {
        if ('main' === $event->getFirewallName() && $event->getUser() instanceof SecurityUser) {
            $event->getRequest()->getSession()->set(self::KEY, $this->policy->start($this->clock->now()->getTimestamp()));
        }
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: 0)]
    public function activity(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if ($event->isMainRequest() && $request->attributes->get(self::EXPIRED_PUBLIC)) {
            $event->getResponse()->headers->set('Cache-Control', 'private, no-store');
            $this->events->dispatch(new SessionExpiredEvent($request, $event->getResponse()));

            return;
        }
        $now = $request->attributes->get(self::CHECKED);
        if (!$event->isMainRequest() || !is_int($now) || $this->automatic($request)
            || $event->getResponse()->getStatusCode() >= 500 || !($this->tokens->getToken()?->getUser() instanceof SecurityUser)) {
            return;
        }
        $session = $request->getSession();
        // A new login can have replaced the state while handling this request.
        $state = $session->get(self::KEY);
        if (!$this->policy->expired($state, $now)) {
            $session->set(self::KEY, $this->policy->activity($state, $now));
        }
    }

    private function automatic(Request $request): bool
    {
        if ('quarter_subscription' === $request->attributes->get('_route')) {
            return true;
        }

        return in_array($request->attributes->get('_route'), ['client_movements', 'firm_client_quarter'], true)
            && '1' === $request->headers->get('X-Quarter-Fragment')
            && 'manual' !== $request->headers->get('X-Session-Activity');
    }
}
