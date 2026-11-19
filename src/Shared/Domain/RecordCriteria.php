<?php

namespace App\Shared\Domain;

final readonly class RecordCriteria
{
    public function __construct(public int $offset = 0, public int $limit = 25, public string $search = '', public int $column = 0, public string $order = 'asc', public string $filter = '', public string $section = '', public string $owner = '', public bool $count = true)
    {
        if ($offset < 0 || $offset > 1_000_000 || $limit < 1 || $limit > 100 || mb_strlen($search) > 120 || $column < 0 || $column > 20 || !in_array($order, ['asc', 'desc'], true) || mb_strlen($filter) > 60 || !in_array($section, ['', 'submitted', 'rejected', 'validated', 'personal'], true)) {
            throw new \InvalidArgumentException('Invalid listing criteria.');
        }
        if ('' !== $owner && 1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $owner)) {
            throw new \InvalidArgumentException('Invalid owner.');
        }
    }
}
