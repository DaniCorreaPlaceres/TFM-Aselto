<?php

namespace App\Shared\Domain;

interface SecurityJournal
{
    public function append(SecurityEntry $entry): void;

    /** @return iterable<string, SecurityEntry> Cursor => entry */
    public function scan(?string $after = null): iterable;

    public function purge(): int;

    public function healthy(): bool;
}
