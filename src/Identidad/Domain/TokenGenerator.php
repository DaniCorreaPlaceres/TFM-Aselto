<?php

namespace App\Identidad\Domain;

interface TokenGenerator
{
    public function next(): string;
}
