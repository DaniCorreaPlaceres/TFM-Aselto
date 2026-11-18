<?php

namespace App\Identidad\Domain;

enum InvitationTarget: string
{
    case Advisory = 'advisory';
    case SelfEmployed = 'self_employed';
    case Company = 'company';
}
