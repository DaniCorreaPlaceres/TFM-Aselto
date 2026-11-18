<?php

namespace App\Identidad\Domain;

interface EmailChangeNotifier
{
    public function request(string $email, string $token): void;

    public function confirmed(string $oldEmail): void;
}
