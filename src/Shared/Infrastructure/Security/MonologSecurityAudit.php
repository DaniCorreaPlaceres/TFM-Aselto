<?php

namespace App\Shared\Infrastructure\Security;

use App\Shared\Domain\Clock;
use App\Shared\Domain\SecurityAudit;
use App\Shared\Domain\SecurityEntry;
use App\Shared\Domain\SecurityEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

final class MonologSecurityAudit implements SecurityAudit
{
    public const CORRELATION = '_security_correlation';
    public const ACTOR = '_security_actor';
    private readonly string $cliCorrelation;

    public function __construct(#[Autowire(service: 'monolog.logger.security_audit')] private readonly LoggerInterface $logger, private readonly RequestStack $requests, private readonly Clock $clock, #[Autowire('%kernel.secret%')] private readonly string $secret)
    {
        $this->cliCorrelation = bin2hex(random_bytes(16));
    }

    public function record(SecurityEvent $event, ?string $actorId = null, ?string $targetId = null, ?string $subject = null): void
    {
        try {
            $request = $this->requests->getCurrentRequest();
            $correlation = $request?->attributes->get(self::CORRELATION);
            if (!is_string($correlation) || 1 !== preg_match('/^[a-f0-9]{32}$/', $correlation)) {
                $correlation = null === $request ? $this->cliCorrelation : bin2hex(random_bytes(16));
                $request?->attributes->set(self::CORRELATION, $correlation);
            }
            $route = $request?->attributes->get('_route');
            $route = is_string($route) && preg_match('/^[a-z_][a-z0-9_]{0,79}$/', $route) ? $route : null;
            $method = $request?->getMethod();
            $method = null === $method ? null : (in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true) ? $method : 'OTHER');
            $actorId ??= $request?->attributes->get(self::ACTOR);
            $ip = $request?->getClientIp();
            $subject = null === $subject ? null : (preg_match('//u', $subject) ? mb_strtolower(trim($subject)) : strtolower(trim($subject)));
            $entry = new SecurityEntry(Uuid::v4()->toRfc4122(), $this->clock->now(), $event, $correlation, $this->uuid($actorId), $this->uuid($targetId), $route, $method, null === $ip ? null : hash_hmac('sha256', 'audit-ip|'.$ip, $this->secret), null === $subject ? null : hash_hmac('sha256', 'audit-subject|'.$subject, $this->secret));
            $this->logger->info('security_event', ['entry' => $entry]);
        } catch (\Throwable) {
            error_log('Aselto: private security audit unavailable.');
        }
    }

    private function uuid(mixed $value): ?string
    {
        return is_string($value) && 1 === preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value) ? $value : null;
    }
}
