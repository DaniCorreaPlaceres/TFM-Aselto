<?php

namespace App\Identidad\Domain;

interface AccountTransaction
{
    public function forUser(string $userId, callable $operation): void;
}
