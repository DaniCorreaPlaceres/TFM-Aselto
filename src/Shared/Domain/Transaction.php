<?php

namespace App\Shared\Domain;

interface Transaction
{
    public function run(callable $operation): void;
}
