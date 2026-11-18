<?php

namespace App\Asesoria\Domain;

interface FirmAdministrationTransaction
{
    public function run(string $firmId, callable $operation): void;
}
