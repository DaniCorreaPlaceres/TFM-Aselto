<?php

namespace App\Asesoria\Domain;

final class Firm
{
    public function __construct(
        private readonly string $id,
        private string $name,
    ) {
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id)) {
            throw new InvalidFirm('The id is not a valid UUID.');
        }

        $name = trim($name);

        if ('' === $name || mb_strlen($name) > 120) {
            throw new InvalidFirm('The firm name is empty or too long.');
        }

        $this->name = $name;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }
}
