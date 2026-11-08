<?php

namespace App\Documento\Infrastructure\Persistence;

use App\Documento\Domain\DocumentDetails;
use App\Documento\Domain\EquivalenceSurcharge;
use App\Documento\Domain\MovementDirection;
use App\Documento\Domain\ReviewAction;
use App\Documento\Domain\ReviewRecord;
use App\Documento\Domain\SupportingDocument;
use App\Documento\Domain\Tax;
use App\Documento\Domain\TaxRate;
use App\Documento\Domain\WithholdingRate;

final class DocumentReviewData
{
    /** @return array<string, mixed> */
    public static function details(DocumentDetails $details): array
    {
        return [
            'direction' => $details->direction()?->value,
            'issued_on' => $details->issuedOn()?->format('Y-m-d'),
            'party_name' => $details->partyName(),
            'tax_id' => $details->taxId(),
            'base_cents' => $details->baseCents(),
            'tax' => $details->tax()->value,
            'tax_rate' => $details->taxRate()?->basisPoints(),
            'surcharge' => $details->surcharge()?->value,
            'withholding_rate' => $details->withholdingRate()?->basisPoints(),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function restoreDetails(array $data): DocumentDetails
    {
        $tax = Tax::from((string) $data['tax']);

        return new DocumentDetails(
            null === $data['direction'] ? null : MovementDirection::from((string) $data['direction']),
            null === $data['issued_on'] ? null : new \DateTimeImmutable((string) $data['issued_on']),
            null === $data['party_name'] ? null : (string) $data['party_name'],
            null === $data['tax_id'] ? null : (string) $data['tax_id'],
            null === $data['base_cents'] ? null : (int) $data['base_cents'],
            null === $data['tax_rate'] ? null : TaxRate::fromBasisPoints($tax, (int) $data['tax_rate']),
            $tax,
            null === $data['surcharge'] ? null : EquivalenceSurcharge::from((int) $data['surcharge']),
            null === $data['withholding_rate'] ? null : WithholdingRate::fromBasisPoints((int) $data['withholding_rate']),
        );
    }

    /** @return array<string, mixed> */
    public static function record(ReviewRecord $record): array
    {
        return ['action' => $record->action->value, 'actor_id' => $record->actorId, 'occurred_at' => $record->occurredAt->format('Y-m-d\TH:i:s.uP'), 'reason' => $record->reason, 'before' => self::details($record->before), 'after' => null === $record->after ? null : self::details($record->after), 'supporting_file_id' => $record->supportingFileId];
    }

    /** @param array<string, mixed> $data */
    public static function restoreRecord(array $data): ReviewRecord
    {
        return new ReviewRecord(ReviewAction::from((string) $data['action']), (string) $data['actor_id'], new \DateTimeImmutable((string) $data['occurred_at']), null === $data['reason'] ? null : (string) $data['reason'], self::restoreDetails($data['before']), null === $data['after'] ? null : self::restoreDetails($data['after']), null === $data['supporting_file_id'] ? null : (string) $data['supporting_file_id']);
    }

    /** @return array<string, mixed> */
    public static function file(SupportingDocument $file): array
    {
        return ['id' => $file->id, 'original_name' => $file->originalName, 'stored_path' => $file->storedPath, 'media_type' => $file->mediaType, 'uploaded_at' => $file->uploadedAt->format('Y-m-d\TH:i:s.uP')];
    }

    /** @param array<string, mixed> $data */
    public static function restoreFile(array $data): SupportingDocument
    {
        return new SupportingDocument((string) $data['id'], (string) $data['original_name'], (string) $data['stored_path'], (string) $data['media_type'], new \DateTimeImmutable((string) $data['uploaded_at']));
    }
}
