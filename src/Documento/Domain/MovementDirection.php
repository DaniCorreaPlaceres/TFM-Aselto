<?php

namespace App\Documento\Domain;

enum MovementDirection: string
{
    case Income = 'income';
    case Expense = 'expense';
}
