<?php

namespace App\Identidad\Infrastructure\Security;

use App\Identidad\Domain\TokenGenerator;

final class RandomTokenGenerator implements TokenGenerator
{
    public function next(): string
    {
        return bin2hex(random_bytes(32));
    }
}
