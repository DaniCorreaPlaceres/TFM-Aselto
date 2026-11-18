<?php

namespace App\Finanzas\Domain;

final class QuarterPeriod
{
    public function __construct(public readonly int $year, public readonly int $number)
    {
        if ($year < 1 || $year > 9999 || $number < 1 || $number > 4) {
            throw new InvalidQuarter('The quarter is not valid.');
        }
    }

    public static function forDate(\DateTimeImmutable $date): self
    {
        return new self((int) $date->format('Y'), intdiv((int) $date->format('n') - 1, 3) + 1);
    }

    public function startsOn(): string
    {
        return sprintf('%04d-%02d-01', $this->year, ($this->number - 1) * 3 + 1);
    }

    public function endsBefore(): string
    {
        return (new \DateTimeImmutable($this->startsOn()))->modify('+3 months')->format('Y-m-d');
    }

    public function contains(\DateTimeImmutable $date): bool
    {
        return (int) $date->format('Y') === $this->year && intdiv((int) $date->format('n') - 1, 3) + 1 === $this->number;
    }

    public function key(): string
    {
        return sprintf('%04d-Q%d', $this->year, $this->number);
    }
}
