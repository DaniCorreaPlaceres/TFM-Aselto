<?php

namespace App\Documento\Ui;

use App\Documento\Domain\DocumentChanged;
use App\Documento\Domain\DocumentNotFound;
use App\Documento\Domain\IncompleteDocument;
use App\Documento\Domain\InvalidDocument;
use App\Documento\Domain\InvalidSurcharge;
use App\Documento\Domain\InvalidTaxRate;
use App\Documento\Domain\InvalidWithholding;
use App\Documento\Domain\MissingClientProfile;
use App\Documento\Domain\MissingFiscalProvince;
use App\Documento\Domain\UnsupportedFile;
use App\Shared\Domain\ClosedPeriod;
use App\Shared\Domain\DocumentQuotaExceeded;
use App\Shared\Ui\ApiError;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -32)]
final class ApiDocumentExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!preg_match('#^/api/v1/(?:advisory/)?documents(?:/|$)#', $event->getRequest()->getPathInfo())) {
            return;
        }
        $error = $event->getThrowable();
        $result = match (true) {
            $error instanceof DocumentQuotaExceeded => [409, 'document_quota_exceeded', 'Has alcanzado los 20 documentos de este mes en Gratis. Espera al próximo mes o solicita un cambio de plan.'],
            $error instanceof DocumentNotFound, $error instanceof MissingClientProfile => [404, 'document_not_found', 'No encontramos el documento solicitado.'],
            $error instanceof DocumentChanged => [409, 'document_changed', 'El registro ha cambiado. Consulta su versión actual antes de guardar.'],
            $error instanceof ClosedPeriod => [409, 'quarter_closed', 'El trimestre está cerrado. Reábrelo antes de modificar sus registros.'],
            $error instanceof IncompleteDocument => [422, 'incomplete_document', 'Faltan datos obligatorios para confirmar o validar el registro.'],
            $error instanceof InvalidTaxRate => [422, 'invalid_tax_rate', 'Revisa el porcentaje del impuesto del documento.'],
            $error instanceof InvalidSurcharge => [422, 'invalid_surcharge', 'El recargo no es compatible con el tipo de gasto y su IVA.'],
            $error instanceof InvalidWithholding => [422, 'invalid_withholding', 'La retención solo se admite en ingresos de autónomos con un porcentaje válido.'],
            $error instanceof MissingFiscalProvince => [422, 'missing_fiscal_province', 'Completa tu provincia en el perfil fiscal antes de subir un documento.'],
            $error instanceof UnsupportedFile => [422, 'unsupported_file', 'El archivo debe ser PDF, JPEG, PNG o WebP y ocupar como máximo 8 MB.'],
            $error instanceof InvalidDocument => [422, 'invalid_document', 'Revisa los datos y el estado del registro. Si se ha solicitado un justificante, debes aportarlo antes de validar.'],
            default => null,
        };
        if (null === $result) {
            return;
        }
        [$status, $code, $message] = $result;
        $response = ApiError::response($status, $code, $message);
        if ($error instanceof IncompleteDocument) {
            $names = ['issuedOn' => 'issued_on', 'partyName' => 'party_name', 'taxId' => 'tax_id', 'taxRate' => 'tax_rate'];
            $response->setData(['error' => ['code' => $code, 'message' => $message, 'fields' => array_map(static fn (string $field): string => $names[$field] ?? $field, $error->fields())]]);
        }
        $event->setResponse($response);
    }
}
