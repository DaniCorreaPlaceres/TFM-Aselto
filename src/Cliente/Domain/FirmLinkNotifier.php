<?php

namespace App\Cliente\Domain;

interface FirmLinkNotifier
{
    public function send(string $email, string $firmName, string $token): void;
}
