<?php

namespace App\Identidad\Domain;

final class PasswordRejected extends \DomainException
{
    public const COMMON = 'common';
    public const TOO_LONG = 'too_long';
    public const INVALID_ENCODING = 'invalid_encoding';

    public function __construct(public readonly string $reason)
    {
        parent::__construct('Password rejected: '.$reason);
    }
}
