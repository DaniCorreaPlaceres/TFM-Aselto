<?php

namespace App\Shared\Infrastructure\Http;

use App\Identidad\Infrastructure\Security\SecurityUser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final readonly class ResourceLimits
{
    public function __construct(
        private RateLimiterFactoryInterface $resourceUploadActorLimiter,
        private RateLimiterFactoryInterface $resourceUploadFirmLimiter,
        private RateLimiterFactoryInterface $resourceReadActorLimiter,
        private RateLimiterFactoryInterface $resourceReadFirmLimiter,
        private RateLimiterFactoryInterface $resourceExportActorLimiter,
        private RateLimiterFactoryInterface $resourceExportFirmLimiter,
        private RateLimiterFactoryInterface $resourceWriteActorLimiter,
        private RateLimiterFactoryInterface $resourceWriteFirmLimiter,
        #[Autowire('%kernel.secret%')] private string $secret,
    ) {
    }

    public function consume(SecurityUser $actor, string $operation): int
    {
        [$personal, $firm] = match ($operation) {
            'upload' => [$this->resourceUploadActorLimiter, $this->resourceUploadFirmLimiter],
            'export' => [$this->resourceExportActorLimiter, $this->resourceExportFirmLimiter],
            'write' => [$this->resourceWriteActorLimiter, $this->resourceWriteFirmLimiter],
            default => [$this->resourceReadActorLimiter, $this->resourceReadFirmLimiter],
        };
        $retry = $this->take($personal, 'actor:'.$actor->id());
        if (0 === $retry && null !== $actor->firmId()) {
            $retry = $this->take($firm, 'firm:'.$actor->firmId());
        }

        return $retry;
    }

    private function take(RateLimiterFactoryInterface $factory, string $key): int
    {
        $limit = $factory->create(hash_hmac('sha256', $key, $this->secret))->consume();

        return $limit->isAccepted() ? 0 : max(1, $limit->getRetryAfter()->getTimestamp() - time());
    }
}
