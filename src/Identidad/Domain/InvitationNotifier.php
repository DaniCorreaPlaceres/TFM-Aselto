<?php

namespace App\Identidad\Domain;

interface InvitationNotifier
{
    public function send(string $email, InvitationTarget $target, string $token): void;
}
