<?php

namespace App\Shared\Domain;

interface SecurityAudit
{
    public function record(SecurityEvent $event, ?string $actorId = null, ?string $targetId = null, ?string $subject = null): void;
}
