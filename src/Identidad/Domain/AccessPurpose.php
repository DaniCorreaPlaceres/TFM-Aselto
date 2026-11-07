<?php

namespace App\Identidad\Domain;

enum AccessPurpose: string
{
    case Activation = 'activation';
    case Reset = 'reset';
}
