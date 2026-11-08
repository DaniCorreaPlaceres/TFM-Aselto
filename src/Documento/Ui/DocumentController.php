<?php

namespace App\Documento\Ui;

use App\Cliente\Application\ShowClientHome;
use App\Documento\Application\ConfirmDocument;
use App\Documento\Application\DocumentForm;
use App\Documento\Application\DocumentWithholdingPolicy;
use App\Documento\Application\ListClientDocuments;
use App\Documento\Application\OpenClientDocument;
use App\Documento\Application\SaveDocumentDraft;
use App\Documento\Application\StoreClientDocument;
use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentFileStore;
use App\Documento\Domain\DocumentNotFound;
use App\Documento\Domain\IncompleteDocument;
use App\Documento\Domain\InvalidDocument;
use App\Documento\Domain\InvalidSurcharge;
use App\Documento\Domain\InvalidTaxRate;
use App\Documento\Domain\InvalidWithholding;
use App\Documento\Domain\MissingClientProfile;
use App\Documento\Domain\MissingFiscalProvince;
use App\Documento\Domain\MovementDirection;
use App\Documento\Domain\ProposalOrigin;
use App\Documento\Domain\Tax;
use App\Documento\Domain\TaxRate;
use App\Documento\Domain\UnsupportedFile;
use App\Identidad\Domain\Role;
use App\Identidad\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DocumentController extends AbstractController
{
    private const ID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    #[Route('/documentos', name: 'client_documents', methods: ['GET'])]
    public function index(ShowClientHome $showClientHome, ListClientDocuments $listClientDocuments): Response
    {
        $user = $this->client();
        $documents = [];

        foreach ($listClientDocuments->execute($user->id()) as $document) {
            $documents[] = [
                'id' => $document->id(),
                'name' => $document->partyName() ?: $document->originalName(),
                'status' => DocumentLabel::status($document->status()),
                'direction' => DocumentLabel::direction($document->direction()),
            ];
        }

        return $this->render('client/documents.html.twig', [
            ...$this->layout($showClientHome, $user, 'documentos'),
            'documents' => $documents,
        ]);
    }

    #[Route('/documentos/nuevo', name: 'client_document_new', methods: ['GET', 'POST'])]
    public function upload(Request $request, ShowClientHome $showClientHome, StoreClientDocument $storeClientDocument): Response
    {
        $user = $this->client();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('upload_document', (string) $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'La sesión del formulario ha caducado. Vuelve a intentarlo.');
            } else {
                $file = $request->files->get('file');

                try {
                    if (!$file instanceof UploadedFile || !$file->isValid()) {
                        throw new UnsupportedFile('The file is missing.');
                    }

                    $document = $storeClientDocument->execute(
                        $user->id(),
                        $file->getClientOriginalName(),
                        (string) $file->getMimeType(),
                        (string) file_get_contents($file->getPathname()),
                    );
                    [$kind, $message] = match ($document->proposal()) {
                        ProposalOrigin::Extracted => ['success', 'Hemos extraído estos datos. Revísalos antes de confirmar.'],
                        ProposalOrigin::Sample => ['warning', 'No hay lectura automática. Estos datos son un ejemplo. Revísalos antes de confirmar.'],
                        null => ['error', 'No hemos podido leer el documento. Escribe los datos.'],
                    };
                    $this->addFlash($kind, $message);

                    return $this->redirectToRoute('client_document', ['id' => $document->id()]);
                } catch (UnsupportedFile) {
                    $this->addFlash('error', 'El archivo tiene que ser PDF, JPEG, PNG o WebP, y ocupar menos de 8 MB.');
                } catch (MissingFiscalProvince) {
                    $this->addFlash('error', 'Completa la provincia en tus datos fiscales antes de añadir un documento.');

                    return $this->redirectToRoute('client_profile');
                } catch (MissingClientProfile) {
                    $this->addFlash('error', 'No encontramos tu ficha de cliente.');
                }
            }
        }

        return $this->render('client/document_new.html.twig', $this->layout($showClientHome, $user, 'documentos'));
    }

    #[Route('/documentos/{id}', name: 'client_document', methods: ['GET', 'POST'], requirements: ['id' => self::ID])]
    public function show(
        string $id,
        Request $request,
        ShowClientHome $showClientHome,
        OpenClientDocument $openClientDocument,
        SaveDocumentDraft $saveDocumentDraft,
        ConfirmDocument $confirmDocument,
        DocumentWithholdingPolicy $withholdingPolicy,
    ): Response {
        $user = $this->client();

        try {
            $document = $openClientDocument->execute($user->id(), $id);
        } catch (DocumentNotFound) {
            throw $this->createNotFoundException();
        }

        $fieldErrors = [];
        $input = $this->inputFromDocument($document);

        if ($request->isMethod('POST') && $document->isEditable()) {
            $input = $this->inputFromRequest($request);

            if (!$this->isCsrfTokenValid('save_document', (string) $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'La sesión del formulario ha caducado. Vuelve a intentarlo.');
            } else {
                try {
                    $saved = 'confirm' === $request->request->get('intent')
                        ? $confirmDocument->execute($user->id(), $id, ...$this->arguments($input))
                        : $saveDocumentDraft->execute($user->id(), $id, ...$this->arguments($input));
                    $this->addFlash('success', $saved->isEditable()
                        ? 'El borrador está guardado.'
                        : 'Has confirmado el registro.');

                    return $this->redirectToRoute('client_document', ['id' => $id]);
                } catch (IncompleteDocument $exception) {
                    $fieldErrors = $this->fieldErrors($exception, $document->tax());
                    $this->addFlash('error', 'Revisa los campos marcados.');
                } catch (InvalidWithholding) {
                    $fieldErrors['withholdingRate'] = 'La retención solo se admite en ingresos de autónomos. Escribe un porcentaje entre 0 y 100, con hasta dos decimales, o deja el campo vacío.';
                    $this->addFlash('error', 'Revisa la retención de IRPF.');
                } catch (InvalidSurcharge) {
                    $fieldErrors['surcharge'] = 'El recargo solo se admite en gastos con IVA y debe corresponder con su tipo. Revisa la selección.';
                    $this->addFlash('error', 'Revisa el recargo de equivalencia.');
                } catch (InvalidTaxRate) {
                    $fieldErrors['taxRate'] = $this->rateError($document->tax());
                    $this->addFlash('error', 'Revisa el porcentaje del impuesto.');
                } catch (InvalidDocument) {
                    $this->addFlash('error', 'Revisa los importes. Usa la coma para los céntimos, por ejemplo 1250,50.');
                } catch (DocumentNotFound) {
                    throw $this->createNotFoundException();
                }
            }
        }

        return $this->render('client/document.html.twig', [
            ...$this->layout($showClientHome, $user, 'documentos'),
            'document' => $this->viewDocument($document, $input, $withholdingPolicy->allows($user->id())),
            'fieldErrors' => $fieldErrors,
            'hasFirm' => null !== $document->firmId(),
        ]);
    }

    #[Route('/documentos/{id}/archivo', name: 'client_document_file', methods: ['GET'], requirements: ['id' => self::ID])]
    public function download(string $id, OpenClientDocument $openClientDocument, DocumentFileStore $files): Response
    {
        $user = $this->client();

        try {
            $document = $openClientDocument->execute($user->id(), $id);
            $contents = $files->read($document->storedPath());
        } catch (DocumentNotFound) {
            throw $this->createNotFoundException();
        }

        $response = new Response($contents);
        $response->headers->set('Content-Type', $document->mediaType());
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $document->originalName(),
        ));

        return $response;
    }

    private function rateMessage(Tax $tax): string
    {
        return Tax::Ipsi === $tax ? 'Indica el porcentaje de IPSI.' : 'Elige el tipo de '.mb_strtoupper($tax->value).'.';
    }

    private function rateError(Tax $tax): string
    {
        return Tax::Ipsi === $tax
            ? 'Escribe un porcentaje entre 0 y 100, con hasta dos decimales.'
            : 'Elige uno de los tipos de '.mb_strtoupper($tax->value).' de la lista.';
    }

    private function client(): SecurityUser
    {
        $user = $this->getUser();

        if (!$user instanceof SecurityUser || Role::Client !== $user->role()) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    /**
     * @return array{section: string, email: string, name: ?string, firmName: ?string}
     */
    private function layout(ShowClientHome $showClientHome, SecurityUser $user, string $section): array
    {
        $home = $showClientHome->execute($user->id());

        return [
            'section' => $section,
            'email' => $user->getUserIdentifier(),
            'name' => $home->name(),
            'firmName' => $home->firmName(),
        ];
    }

    /**
     * @param array{direction: string, issuedOn: string, partyName: string, taxId: string, base: string, taxRate: string, surcharge: string, withholdingRate: string} $input
     *
     * @return array<string, mixed>
     */
    private function viewDocument(Document $document, array $input, bool $allowsWithholding): array
    {
        return [
            'id' => $document->id(),
            'originalName' => $document->originalName(),
            'image' => str_starts_with($document->mediaType(), 'image/'),
            'editable' => $document->isEditable(),
            'proposal' => $document->proposal()?->value,
            'status' => DocumentLabel::status($document->status()),
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
            $details = DocumentForm::read(
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

    /**
     * @return array<string, string>
     */
    private function fieldErrors(IncompleteDocument $exception, Tax $tax): array
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
    private function inputFromDocument(Document $document): array
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
    private function inputFromRequest(Request $request): array
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
    private function arguments(array $input): array
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
