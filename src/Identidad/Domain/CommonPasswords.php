<?php

namespace App\Identidad\Domain;

interface CommonPasswords
{
    public function contains(string $password): bool;
}
