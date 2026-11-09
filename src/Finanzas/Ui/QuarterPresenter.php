<?php

namespace App\Finanzas\Ui;

use App\Finanzas\Application\QuarterView;
use App\Identidad\Domain\UserRepository;

final class QuarterPresenter
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    /** @return array<string, mixed> */
    public function view(QuarterView $view): array
    {
        $summary = $view->record?->closed ? $view->record->snapshot : $view->calculation->summary;
        $events = [];
        foreach ((null === $view->record ? [] : $view->record->history) as $event) {
            $events[] = ['closed' => $event->closed, 'author' => $this->users->find($event->actorId)?->email() ?? 'Autor no disponible', 'date' => $event->at->setTimezone(new \DateTimeZone('Atlantic/Canary'))->format('d/m/Y H:i T'), 'snapshot' => $event->snapshot];
        }

        return ['quarter' => $view, 'summary' => $summary, 'events' => $events, 'canManage' => $view->access->mayManage($view->firmId, $view->record)];
    }
}
