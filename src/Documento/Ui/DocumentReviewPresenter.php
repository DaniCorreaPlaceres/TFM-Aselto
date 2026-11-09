<?php

namespace App\Documento\Ui;

use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentDetails;
use App\Documento\Domain\ReviewAction;
use App\Identidad\Domain\UserRepository;

final class DocumentReviewPresenter
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    /** @return list<array<string, mixed>> */
    public function history(Document $document): array
    {
        $history = [];
        foreach ($document->reviewHistory() as $record) {
            $changes = [];
            if (null !== $record->after) {
                $before = $this->details($record->before);
                foreach ($this->details($record->after) as $field => $value) {
                    if ($value !== $before[$field]) {
                        $changes[] = $field.': '.$before[$field].' → '.$value;
                    }
                }
            }
            $history[] = [
                'action' => match ($record->action) {
                    ReviewAction::Amended => 'Corrección iniciada',
                    ReviewAction::Submitted => 'Registro enviado',
                    ReviewAction::Validated => 'Registro validado',
                    ReviewAction::Rejected => 'Registro rechazado',
                    ReviewAction::Requested => 'Justificante solicitado',
                    ReviewAction::Attached => 'Justificante aportado',
                },
                'author' => $this->users->find($record->actorId)?->email() ?? 'Autor no disponible',
                'date' => $record->occurredAt->format('d/m/Y H:i T'),
                'reason' => $record->reason,
                'changes' => $changes,
            ];
        }

        return $history;
    }

    /** @return list<array{id: string, name: string, date: string}> */
    public function files(Document $document): array
    {
        return array_map(static fn ($file): array => ['id' => $file->id, 'name' => $file->originalName, 'date' => $file->uploadedAt->format('d/m/Y H:i T')], $document->supportingFiles());
    }

    /** @return array<string, string> */
    private function details(DocumentDetails $details): array
    {
        return [
            'Tipo' => DocumentLabel::direction($details->direction()),
            'Fecha' => $details->issuedOn()?->format('d/m/Y') ?? 'Sin indicar',
            'Tercero' => $details->partyName() ?? 'Sin indicar',
            'NIF' => $details->taxId() ?? 'Sin indicar',
            'Base' => DocumentLabel::euros($details->baseCents()).' €',
            'Impuesto' => mb_strtoupper($details->tax()->value).' '.($details->taxRate()?->percentage() ?? '').' %',
            'Recargo' => $details->surcharge()?->percentage() ?? 'Sin recargo',
            'Retención' => $details->withholdingRate()?->percentage() ?? 'Sin retención',
            'Total' => DocumentLabel::euros($details->totalCents()).' €',
        ];
    }
}
