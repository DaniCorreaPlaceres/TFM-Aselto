<?php

namespace App\Documento\Ui;

use App\Documento\Application\DocumentForm;
use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentDetails;
use App\Documento\Domain\IncompleteDocument;
use App\Documento\Domain\InvalidDocument;
use App\Documento\Domain\InvalidSurcharge;
use App\Documento\Domain\InvalidTaxRate;
use App\Documento\Domain\InvalidWithholding;
use App\Documento\Domain\MovementDirection;
use App\Documento\Domain\Tax;
use App\Documento\Domain\TaxRate;
use Symfony\Component\HttpFoundation\Request;

final class DocumentReviewForm
{
    private function rateMessage(Tax $tax): string
    {
        return Tax::Ipsi === $tax ? 'Indica el porcentaje de IPSI.' : 'Elige el tipo de '.mb_strtoupper($tax->value).'.';
    }

    public function rateError(Tax $tax): string
    {
        return Tax::Ipsi === $tax
            ? 'Escribe un porcentaje entre 0 y 100, con hasta dos decimales.'
            : 'Elige uno de los tipos de '.mb_strtoupper($tax->value).' de la lista.';
    }

    /**
     * @param array{direction: string, issuedOn: string, partyName: string, taxId: string, base: string, taxRate: string, surcharge: string, withholdingRate: string} $input
     *
     * @return array<string, mixed>
     */
    public function viewDocument(Document $document, array $input, bool $allowsWithholding): array
    {
        return [
            'id' => $document->id(),
            'version' => $document->version(),
            'statusCode' => $document->status()->value,
            'pendingRequest' => $document->pendingRequest(),
            'originalName' => $document->originalName(),
            'image' => str_starts_with($document->mediaType(), 'image/'),
            'editable' => $document->isEditable(),
            'proposal' => $document->proposal()?->value,
            'status' => DocumentLabel::forDocument($document),
            'taxName' => mb_strtoupper($document->tax()->value),
            'allowsWithholding' => $allowsWithholding,
            'withholdingRate' => $document->withholdingRate()?->percentage() ?? '',
            'withholdingQuota' => DocumentLabel::euros($document->withholdingQuotaCents()),
            'supportsSurcharge' => Tax::Vat === $document->tax(),
            'surcharge' => $document->surcharge()?->percentage() ?? '',
            'surchargeQuota' => DocumentLabel::euros($document->surchargeQuotaCents()),
            'manualTaxRate' => Tax::Ipsi === $document->tax(),
            'taxRates' => array_map(static fn (int $rate): string => TaxRate::fromBasisPoints($document->tax(), $rate)->percentage(), $document->tax()->allowedBasisPoints()),
            'rateMessage' => $this->rateMessage($document->tax()),
            'rateError' => $this->rateError($document->tax()),
            'quota' => DocumentLabel::euros($document->quotaCents()),
            'directionLabel' => DocumentLabel::direction($document->direction()),
            'issuedOnLabel' => $document->issuedOn()?->format('d/m/Y') ?? '',
            'baseLabel' => DocumentLabel::euros($document->baseCents()),
            'totalLabel' => DocumentLabel::euros($document->totalCents()),
            'partyName' => $document->partyName() ?? '',
            'taxId' => $document->taxId() ?? '',
            'taxRate' => $document->taxRate()?->percentage() ?? '',
            ...$input,
            ...$this->amounts($input, $document->tax()),
        ];
    }

    /**
     * @param array{direction: string, issuedOn: string, partyName: string, taxId: string, base: string, taxRate: string, surcharge: string, withholdingRate: string} $input
     *
     * @return array{quota: string, total: string, surchargeQuota: string, withholdingQuota: string}
     */
    private function amounts(array $input, Tax $tax): array
    {
        try {
            $details = $this->details($input, $tax);
        } catch (InvalidDocument|InvalidTaxRate|InvalidSurcharge|InvalidWithholding) {
            return ['quota' => '', 'total' => '', 'surchargeQuota' => '', 'withholdingQuota' => ''];
        }

        return [
            'withholdingQuota' => DocumentLabel::euros($details->withholdingQuotaCents()),
            'surchargeQuota' => DocumentLabel::euros($details->surchargeQuotaCents()),
            'quota' => DocumentLabel::euros($details->quotaCents()),
            'total' => DocumentLabel::euros($details->totalCents()),
        ];
    }

    /** @param array{direction: string, issuedOn: string, partyName: string, taxId: string, base: string, taxRate: string, surcharge: string, withholdingRate: string} $input */
    public function details(array $input, Tax $tax): DocumentDetails
    {
        return DocumentForm::read(
            $input['direction'],
            $input['issuedOn'],
            $input['partyName'],
            $input['taxId'],
            $input['base'],
            $input['taxRate'],
            $tax,
            $input['surcharge'],
            $input['withholdingRate'],
        );
    }

    /**
     * @return array<string, string>
     */
    public function fieldErrors(IncompleteDocument $exception, Tax $tax): array
    {
        $messages = [
            'direction' => 'Indica si es un ingreso o un gasto.',
            'issuedOn' => 'Indica la fecha.',
            'partyName' => 'Indica el tercero.',
            'taxId' => 'Indica el NIF.',
            'base' => 'Indica la base.',
            'taxRate' => $this->rateMessage($tax),
        ];
        $errors = [];

        foreach ($exception->fields() as $field) {
            if (isset($messages[$field])) {
                $errors[$field] = $messages[$field];
            }
        }

        return $errors;
    }

    /**
     * @return array{direction: string, issuedOn: string, partyName: string, taxId: string, base: string, taxRate: string, surcharge: string, withholdingRate: string}
     */
    public function inputFromDocument(Document $document): array
    {
        return [
            'direction' => match ($document->direction()) {
                MovementDirection::Income => 'ingreso',
                MovementDirection::Expense => 'gasto',
                null => '',
            },
            'issuedOn' => $document->issuedOn()?->format('Y-m-d') ?? '',
            'partyName' => $document->partyName() ?? '',
            'taxId' => $document->taxId() ?? '',
            'base' => DocumentLabel::euros($document->baseCents()),
            'taxRate' => $document->taxRate()?->percentage() ?? '',
            'surcharge' => $document->surcharge()?->percentage() ?? '',
            'withholdingRate' => $document->withholdingRate()?->percentage() ?? '',
        ];
    }

    /**
     * @return array{direction: string, issuedOn: string, partyName: string, taxId: string, base: string, taxRate: string, surcharge: string, withholdingRate: string}
     */
    public function inputFromRequest(Request $request): array
    {
        return [
            'direction' => (string) $request->request->get('direction', ''),
            'issuedOn' => (string) $request->request->get('issued_on', ''),
            'partyName' => (string) $request->request->get('party_name', ''),
            'taxId' => (string) $request->request->get('tax_id', ''),
            'base' => (string) $request->request->get('base', ''),
            'taxRate' => (string) $request->request->get('tax_rate', ''),
            'surcharge' => (string) $request->request->get('surcharge', ''),
            'withholdingRate' => (string) $request->request->get('withholding_rate', ''),
        ];
    }

    /**
     * @param array{direction: string, issuedOn: string, partyName: string, taxId: string, base: string, taxRate: string, surcharge: string, withholdingRate: string} $input
     *
     * @return array{string, string, string, string, string, string, string, string}
     */
    public function arguments(array $input): array
    {
        return [
            $input['direction'],
            $input['issuedOn'],
            $input['partyName'],
            $input['taxId'],
            $input['base'],
            $input['taxRate'],
            $input['surcharge'],
            $input['withholdingRate'],
        ];
    }
}
