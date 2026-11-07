<?php

namespace App\Shared\Domain;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
