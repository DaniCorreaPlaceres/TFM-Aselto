<?php

namespace App\Finanzas\Infrastructure\Persistence;

use App\Finanzas\Domain\Quarter;
use App\Finanzas\Domain\QuarterPeriod;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'financial_quarter')]
class DoctrineQuarter
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $ownerId;

    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $scope;

    #[ORM\Id]
    #[ORM\Column(type: 'smallint')]
    private int $year;

    #[ORM\Id]
    #[ORM\Column(type: 'smallint')]
    private int $number;

    #[ORM\Column]
    private bool $closed;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $snapshot;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $history;

    #[ORM\Version]
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    public function apply(Quarter $quarter): void
    {
        $this->ownerId = $quarter->ownerId;
        $this->scope = $quarter->firmId ?? 'personal';
        $this->year = $quarter->period->year;
        $this->number = $quarter->period->number;
        $this->closed = $quarter->closed;
        $this->snapshot = QuarterData::summary($quarter->snapshot);
        $this->history = array_map(QuarterData::event(...), $quarter->history);
    }

    public function toDomain(): Quarter
    {
        return new Quarter($this->ownerId, 'personal' === $this->scope ? null : $this->scope, new QuarterPeriod($this->year, $this->number), $this->closed, QuarterData::restoreSummary($this->snapshot), array_map(QuarterData::restoreEvent(...), $this->history), $this->version);
    }
}
