<?php

namespace App\Identidad\Application;

use App\Identidad\Domain\InvitationTarget;

final readonly class InvitationSummary
{
    public function __construct(public string $id, public string $email, public InvitationTarget $target, public \DateTimeImmutable $sentAt, public \DateTimeImmutable $expiresAt, public string $status, public bool $canResend)
    {
    }
}
