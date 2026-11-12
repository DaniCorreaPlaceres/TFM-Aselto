<?php

namespace App\Finanzas\Ui;

use App\Finanzas\Application\QuarterView;
use App\Finanzas\Domain\Quarter;
use App\Finanzas\Domain\QuarterEvent;
use App\Finanzas\Domain\QuarterSummary;

final class ApiQuarterPresenter
{
    /** @return array<string, mixed> */
    public function view(QuarterView $view): array
    {
        return [
            'client_id' => $view->access->clientId,
            'year' => $view->period->year,
            'quarter' => $view->period->number,
            'scope' => $view->firmId ?? 'personal',
            'firm_id' => $view->firmId,
            'closed' => null !== $view->record && $view->record->closed,
            'version' => null === $view->record ? 0 : $view->record->version,
            'fingerprint' => $view->calculation->fingerprint,
            'can_manage' => $view->access->mayManage($view->firmId, $view->record),
            'summary' => $this->summary($view->record?->closed ? $view->record->snapshot : $view->calculation->summary),
            'pending' => array_map(static fn ($record): array => ['id' => $record->id, 'issued_on' => $record->date?->format('Y-m-d'), 'status' => $record->status, 'version' => $record->version], $view->calculation->pending),
            'events' => $this->events($view->record),
            'history' => array_map(fn (Quarter $record): array => ['year' => $record->period->year, 'quarter' => $record->period->number, 'scope' => $record->firmId ?? 'personal', 'closed' => $record->closed, 'version' => $record->version, 'snapshot' => $this->summary($record->snapshot), 'events' => $this->events($record)], $view->history),
        ];
    }

    /** @return array<string, mixed> */
    private function summary(QuarterSummary $summary): array
    {
        return [
            'income_cents' => $summary->incomeCents(),
            'expense_cents' => $summary->expenseCents(),
            'result_cents' => $summary->resultCents(),
            'taxes' => $summary->taxes(),
            'surcharge_cents' => $summary->surchargeCents(),
            'withholding_cents' => $summary->withholdingCents(),
            'movements' => array_map(static fn ($movement): array => ['id' => $movement->id, 'issued_on' => $movement->date->format('Y-m-d'), 'direction' => $movement->income ? 'income' : 'expense', 'party_name' => $movement->partyName, 'base_cents' => $movement->baseCents, 'tax' => $movement->tax, 'tax_cents' => $movement->taxCents, 'surcharge_cents' => $movement->surchargeCents, 'withholding_cents' => $movement->withholdingCents, 'total_cents' => $movement->totalCents], $summary->movements),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function events(?Quarter $record): array
    {
        return array_map(fn (QuarterEvent $event): array => ['closed' => $event->closed, 'actor_id' => $event->actorId, 'at' => $event->at->format(DATE_ATOM), 'snapshot' => $this->summary($event->snapshot)], null === $record ? [] : $record->history);
    }
}
