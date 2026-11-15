<?php

namespace App\Identidad\Infrastructure\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\String\UnicodeString;

final class AuthenticationRateLimits
{
    public function __construct(
        private readonly RateLimiterFactoryInterface $authenticationIpLimiter,
        private readonly RateLimiterFactoryInterface $authenticationIdentityLimiter,
        private readonly RateLimiterFactoryInterface $recoveryIpLimiter,
        private readonly RateLimiterFactoryInterface $recoveryIdentityLimiter,
        #[Autowire('%kernel.secret%')] private readonly string $secret,
    ) {
        if ('' === $secret) {
            throw new \InvalidArgumentException('Authentication limits require a non-empty application secret.');
        }
    }

    public function login(string $ip, string $email): int
    {
        return $this->consume($this->authenticationIpLimiter, $ip)
            ?: $this->consume($this->authenticationIdentityLimiter, $this->normalize($email).'|'.$ip);
    }

    public function recovery(string $ip, string $email): int
    {
        return $this->consume($this->recoveryIpLimiter, $ip)
            ?: $this->consume($this->recoveryIdentityLimiter, $this->normalize($email));
    }

    private function normalize(string $email): string
    {
        $email = trim($email);

        return preg_match('//u', $email) ? (new UnicodeString($email))->folded()->ascii()->lower()->toString() : strtolower($email);
    }

    private function consume(RateLimiterFactoryInterface $factory, string $identity): int
    {
        $limit = $factory->create(hash_hmac('sha256', $identity, $this->secret))->consume();

        return $limit->isAccepted() ? 0 : max(1, $limit->getRetryAfter()->getTimestamp() - time());
    }
}
