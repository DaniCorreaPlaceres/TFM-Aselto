<?php

namespace App\Shared\Domain;

// For domain/application tests constructed without the production container.
final class NullSecurityAudit implements SecurityAudit
{
    public function record(SecurityEvent $event, ?string $actorId = null, ?string $targetId = null, ?string $subject = null): void
    {
    }
}
