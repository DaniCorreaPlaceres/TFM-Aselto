<?php

namespace App\Asesoria\Domain;

interface FirmRepository
{
    public function find(string $id): ?Firm;

    /**
     * @return list<Firm>
     */
    public function all(): array;

    public function save(Firm $firm): void;
}
