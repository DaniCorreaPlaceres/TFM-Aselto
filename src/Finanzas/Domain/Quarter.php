<?php

namespace App\Finanzas\Domain;

final class Quarter
{
    /** @param list<QuarterEvent> $history */
    public function __construct(public readonly string $ownerId, public readonly ?string $firmId, public readonly QuarterPeriod $period, public readonly bool $closed, public readonly QuarterSummary $snapshot, public readonly array $history, public readonly int $version = 1)
    {
    }

    public static function firstClose(string $ownerId, ?string $firmId, QuarterPeriod $period, QuarterSummary $snapshot, string $actorId, \DateTimeImmutable $at): self
    {
        return new self($ownerId, $firmId, $period, true, $snapshot, [new QuarterEvent(true, $actorId, $at, $snapshot)]);
    }

    public function close(QuarterSummary $snapshot, string $actorId, \DateTimeImmutable $at): self
    {
        if ($this->closed) {
            throw new QuarterChanged('The quarter is already closed.');
        }

        return new self($this->ownerId, $this->firmId, $this->period, true, $snapshot, [...$this->history, new QuarterEvent(true, $actorId, $at, $snapshot)], $this->version);
    }

    public function reopen(string $actorId, \DateTimeImmutable $at): self
    {
        if (!$this->closed) {
            throw new QuarterChanged('The quarter is already open.');
        }

        return new self($this->ownerId, $this->firmId, $this->period, false, $this->snapshot, [...$this->history, new QuarterEvent(false, $actorId, $at, $this->snapshot)], $this->version);
    }
}
