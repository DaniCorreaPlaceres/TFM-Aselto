<?php

namespace App\Identidad\Domain;

enum Role: string
{
    case Superadmin = 'superadmin';
    case FirmAdmin = 'firm_admin';
    case Advisor = 'advisor';
    case Client = 'client';
}
