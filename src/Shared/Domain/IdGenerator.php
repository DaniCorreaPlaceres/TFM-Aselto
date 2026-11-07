<?php

namespace App\Shared\Domain;

interface IdGenerator
{
    public function next(): string;
}
