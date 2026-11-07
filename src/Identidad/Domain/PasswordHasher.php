<?php

namespace App\Identidad\Domain;

interface PasswordHasher
{
    public function hash(string $plainPassword): string;
}
