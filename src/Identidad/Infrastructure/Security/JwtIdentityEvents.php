<?php

namespace App\Identidad\Infrastructure\Security;

use App\Identidad\Domain\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class JwtIdentityEvents
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    #[AsEventListener(event: Events::JWT_CREATED)]
    public function created(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof SecurityUser) {
            $event->setData(array_replace($event->getData(), ['uid' => $user->id(), 'auth_version' => $user->authVersion()]));
        }
    }

    #[AsEventListener(event: Events::JWT_DECODED)]
    public function decoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        $email = $payload['username'] ?? null;
        $user = is_string($email) ? $this->users->findByEmail($email) : null;
        if (null === $user || ($payload['uid'] ?? null) !== $user->id() || ($payload['auth_version'] ?? null) !== $user->authVersion()) {
            $event->markAsInvalid();
        }
    }
}
