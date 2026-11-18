<?php

namespace App\Shared\Infrastructure;

use App\Shared\Domain\IdGenerator;
use Symfony\Component\Uid\Uuid;

final class SymfonyIdGenerator implements IdGenerator
{
    public function next(): string
    {
        return Uuid::v4()->toRfc4122();
    }
}
