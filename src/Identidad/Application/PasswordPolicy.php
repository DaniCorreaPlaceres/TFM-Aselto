<?php

namespace App\Identidad\Application;

use App\Identidad\Domain\CommonPasswords;
use App\Identidad\Domain\PasswordRejected;
use App\Identidad\Domain\PasswordTooShort;

final class PasswordPolicy
{
    public const MIN_LENGTH = 15;
    public const MAX_LENGTH = 128;

    public function __construct(private readonly CommonPasswords $commonPasswords)
    {
    }

    public function assertAcceptable(#[\SensitiveParameter] string $password): void
    {
        if (strlen($password) > self::MAX_LENGTH * 4) {
            throw new PasswordRejected(PasswordRejected::TOO_LONG);
        }
        if (!mb_check_encoding($password, 'UTF-8')) {
            throw new PasswordRejected(PasswordRejected::INVALID_ENCODING);
        }
        $length = mb_strlen($password, 'UTF-8');
        if ($length < self::MIN_LENGTH) {
            throw new PasswordTooShort('The password is too short.');
        }
        if ($length > self::MAX_LENGTH) {
            throw new PasswordRejected(PasswordRejected::TOO_LONG);
        }
        // Only the comparison is normalized. The hasher receives the original value.
        $comparison = preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', trim($password));
        if ('' === $comparison || $this->commonPasswords->contains(mb_strtolower($comparison, 'UTF-8'))) {
            throw new PasswordRejected(PasswordRejected::COMMON);
        }
    }
}
