<?php

namespace App\Documento\Ui;

use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentDetails;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ApiDocumentPresenter
{
    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    /** @return array<string, mixed> */
    public function view(Document $document, bool $advisory = false): array
    {
        $route = $advisory ? 'api_advisory_document' : 'api_document';

        return ['id' => $document->id(), 'owner_id' => $document->clientUserId(), 'firm_id' => $document->firmId(), 'original_name' => $document->originalName(), 'media_type' => $document->mediaType(), 'status' => $document->status()->value, 'version' => $document->version(), 'created_at' => $document->createdAt()->format(DATE_ATOM), 'proposal' => $document->proposal()?->value, 'pending_request' => $document->pendingRequest(), ...$this->details($document->details()), 'file_url' => $this->urls->generate($route.'_file', ['id' => $document->id()]), 'supporting_files' => array_map(fn ($file): array => ['id' => $file->id, 'original_name' => $file->originalName, 'media_type' => $file->mediaType, 'uploaded_at' => $file->uploadedAt->format(DATE_ATOM), 'url' => $this->urls->generate($route.'_supporting_file', ['id' => $document->id(), 'fileId' => $file->id])], $document->supportingFiles()), 'history' => array_map(fn ($record): array => ['action' => $record->action->value, 'actor_id' => $record->actorId, 'occurred_at' => $record->occurredAt->format(DATE_ATOM), 'reason' => $record->reason, 'supporting_file_id' => $record->supportingFileId, 'before' => $this->details($record->before), 'after' => null === $record->after ? null : $this->details($record->after)], $document->reviewHistory())];
    }

    /** @return array<string, mixed> */
    private function details(DocumentDetails $details): array
    {
        return ['direction' => $details->direction()?->value, 'issued_on' => $details->issuedOn()?->format('Y-m-d'), 'party_name' => $details->partyName(), 'tax_id' => $details->taxId(), 'tax' => $details->tax()->value, 'base_cents' => $details->baseCents(), 'tax_rate' => $this->percentage($details->taxRate()?->percentage()), 'tax_cents' => $details->quotaCents(), 'surcharge_rate' => $this->percentage($details->surcharge()?->percentage()), 'surcharge_cents' => $details->surchargeQuotaCents(), 'withholding_rate' => $this->percentage($details->withholdingRate()?->percentage()), 'withholding_cents' => $details->withholdingQuotaCents(), 'total_cents' => $details->totalCents()];
    }

    private function percentage(?string $value): ?string
    {
        return null === $value ? null : str_replace(',', '.', $value);
    }
}
