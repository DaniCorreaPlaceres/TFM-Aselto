<?php

namespace App\Identidad\Domain;

interface AccountNotifier
{
    public function sendActivation(string $email, string $token): void;

    public function sendPasswordReset(string $email, string $token): void;
}
