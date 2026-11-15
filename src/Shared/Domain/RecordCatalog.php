<?php

namespace App\Shared\Domain;

interface RecordCatalog
{
    public function page(string $kind, string $scope, RecordCriteria $criteria): RecordPage;

    /** @return array{unread: int, requests: int} */
    public function attention(string $actorId): array;

    /** @template T
     * @param \Closure(): T $read
     *
     * @return T
     */
    public function snapshot(\Closure $read): mixed;
}
