<?php

namespace App\Shared\Infrastructure\Security;

use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Identidad\Infrastructure\Security\SessionExpiredEvent;
use App\Shared\Domain\SecurityAudit;
use App\Shared\Domain\SecurityEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final readonly class SecurityHttpEvents
{
    public function __construct(private SecurityAudit $audit)
    {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 4096)]
    public function correlation(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $event->getRequest()->attributes->set(MonologSecurityAudit::CORRELATION, bin2hex(random_bytes(16)));
        }
    }

    #[AsEventListener(event: LoginSuccessEvent::class, priority: -256)]
    public function login(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof SecurityUser) {
            $event->getRequest()->attributes->set(MonologSecurityAudit::ACTOR, $user->id());
            $this->audit->record(SecurityEvent::LoginSucceeded, $user->id());
        }
    }

    #[AsEventListener(event: LoginFailureEvent::class)]
    public function failure(LoginFailureEvent $event): void
    {
        $badge = $event->getPassport()?->getBadge(UserBadge::class);
        $this->audit->record(SecurityEvent::LoginFailed, subject: $badge instanceof UserBadge ? $badge->getUserIdentifier() : null);
    }

    #[AsEventListener(event: LogoutEvent::class, priority: -256)]
    public function logout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        $this->audit->record(SecurityEvent::Logout, $user instanceof SecurityUser ? $user->id() : null);
    }

    #[AsEventListener(event: SessionExpiredEvent::class)]
    public function expired(SessionExpiredEvent $event): void
    {
        $this->audit->record(SecurityEvent::SessionExpired);
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -4096)]
    public function response(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $correlation = $request->attributes->get(MonologSecurityAudit::CORRELATION);
        if (is_string($correlation) && preg_match('/^[a-f0-9]{32}$/', $correlation)) {
            $event->getResponse()->headers->set('X-Request-ID', $correlation);
        }
        $status = $event->getResponse()->getStatusCode();
        $path = rawurldecode($request->getPathInfo());
        $login = in_array($path, ['/entrar', '/api/v1/auth/login'], true);
        if (429 === $status) {
            $authentication = $login || '/recuperar' === $path;
            $subject = null;
            if ($authentication) {
                try {
                    $data = str_starts_with($path, '/api/') ? $request->toArray() : $request->request->all();
                    $subject = is_string($data['email'] ?? null) ? $data['email'] : null;
                } catch (\Throwable) {
                }
            }
            $this->audit->record($authentication ? SecurityEvent::AuthenticationLimited : SecurityEvent::ResourceLimited, subject: $subject);
        } elseif (403 === $status) {
            $this->audit->record(SecurityEvent::AccessDenied);
        } elseif (401 === $status && !$login) {
            $this->audit->record(SecurityEvent::AuthenticationRejected);
        }
    }
}
