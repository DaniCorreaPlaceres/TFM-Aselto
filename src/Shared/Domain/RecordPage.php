<?php

namespace App\Shared\Domain;

final readonly class RecordPage
{
    /** @param list<array<string, mixed>> $rows */
    public function __construct(public array $rows, public int $total, public int $filtered)
    {
    }
}
