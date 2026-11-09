<?php

namespace App\Shared\Domain;

interface ClientTransaction
{
    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function run(string $ownerId, callable $operation): mixed;
}
