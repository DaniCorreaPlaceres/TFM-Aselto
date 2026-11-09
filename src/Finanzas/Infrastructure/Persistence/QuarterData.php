<?php

namespace App\Finanzas\Infrastructure\Persistence;

use App\Finanzas\Domain\QuarterEvent;
use App\Finanzas\Domain\QuarterMovement;
use App\Finanzas\Domain\QuarterSummary;

final class QuarterData
{
    /** @return list<array<string, mixed>> */
    public static function summary(QuarterSummary $summary): array
    {
        return array_map(static function (QuarterMovement $movement): array {
            $data = get_object_vars($movement);
            $data['date'] = $movement->date->format('Y-m-d');

            return $data;
        }, $summary->movements);
    }

    /** @param list<array<string, mixed>> $data */
    public static function restoreSummary(array $data): QuarterSummary
    {
        return new QuarterSummary(array_map(static fn (array $row): QuarterMovement => new QuarterMovement((string) $row['id'], new \DateTimeImmutable((string) $row['date']), (bool) $row['income'], (string) $row['partyName'], (int) $row['baseCents'], (string) $row['tax'], (int) $row['taxCents'], (int) $row['surchargeCents'], (int) $row['withholdingCents'], (int) $row['totalCents']), $data));
    }

    /** @return array<string, mixed> */
    public static function event(QuarterEvent $event): array
    {
        return ['closed' => $event->closed, 'actor' => $event->actorId, 'at' => $event->at->format('Y-m-d\TH:i:s.uP'), 'snapshot' => self::summary($event->snapshot)];
    }

    /** @param array<string, mixed> $data */
    public static function restoreEvent(array $data): QuarterEvent
    {
        return new QuarterEvent((bool) $data['closed'], (string) $data['actor'], new \DateTimeImmutable((string) $data['at']), self::restoreSummary($data['snapshot']));
    }
}
