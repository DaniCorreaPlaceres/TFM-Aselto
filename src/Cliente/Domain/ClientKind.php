<?php

namespace App\Cliente\Domain;

enum ClientKind: string
{
    case SelfEmployed = 'self_employed';
    case Company = 'company';
}
