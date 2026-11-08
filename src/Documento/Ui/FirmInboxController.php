<?php

namespace App\Documento\Ui;

use App\Documento\Application\DocumentWithholdingPolicy;
use App\Documento\Application\FirmDocumentAccess;
use App\Documento\Application\ListFirmDocuments;
use App\Documento\Application\ReadSupportingDocument;
use App\Documento\Application\ReviewSubmittedDocument;
use App\Documento\Domain\DocumentChanged;
use App\Documento\Domain\DocumentFileStore;
use App\Documento\Domain\DocumentNotFound;
use App\Documento\Domain\DocumentStatus;
use App\Documento\Domain\IncompleteDocument;
use App\Documento\Domain\InvalidDocument;
use App\Documento\Domain\InvalidSurcharge;
use App\Documento\Domain\InvalidTaxRate;
use App\Documento\Domain\InvalidWithholding;
use App\Documento\Domain\ReviewAction;
use App\Identidad\Domain\Role;
use App\Identidad\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FirmInboxController extends AbstractController
{
    private const ID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    #[Route('/bandeja', name: 'firm_inbox', methods: ['GET'])]
    public function index(Request $request, ListFirmDocuments $list): Response
    {
        $user = $this->actor();
        $filter = (string) $request->query->get('status', 'submitted');
        if (!in_array($filter, ['submitted', 'rejected', 'validated'], true)) {
            $filter = 'submitted';
        }
        try {
            $documents = [];
            foreach ($list->execute($user->id()) as $entry) {
                $document = $entry->document;
                if ($document->status()->value === $filter) {
                    $documents[] = ['id' => $document->id(), 'client' => $entry->clientName, 'name' => $document->partyName() ?: $document->originalName(), 'status' => DocumentLabel::forDocument($document), 'total' => DocumentLabel::euros($document->totalCents())];
                }
            }
        } catch (DocumentNotFound) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('firm/inbox.html.twig', ['documents' => $documents, 'filter' => $filter]);
    }

    #[Route('/bandeja/{id}', name: 'firm_document', methods: ['GET', 'POST'], requirements: ['id' => self::ID])]
    public function show(string $id, Request $request, FirmDocumentAccess $access, ReviewSubmittedDocument $review, DocumentReviewForm $form, DocumentReviewPresenter $presenter, DocumentWithholdingPolicy $withholdingPolicy): Response
    {
        $user = $this->actor();
        try {
            $entry = $access->open($user->id(), $id);
        } catch (DocumentNotFound) {
            throw $this->createNotFoundException();
        }
        $document = $entry->document;
        $input = $form->inputFromDocument($document);
        $fieldErrors = [];
        $error = null;
        $reason = (string) $request->request->get('reason', '');
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('review_document_'.$id, (string) $request->request->get('_csrf_token'))) {
                $error = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';
            } else {
                try {
                    $action = match ((string) $request->request->get('intent')) {
                        'confirm' => ReviewAction::Validated,
                        'reject' => ReviewAction::Rejected,
                        'request' => ReviewAction::Requested,
                        default => throw new InvalidDocument('The action is not valid.'),
                    };
                    $details = null;
                    if (ReviewAction::Validated === $action) {
                        $input = $form->inputFromRequest($request);
                        $details = $form->details($input, $document->tax());
                    }
                    $review->execute($user->id(), $id, (int) $request->request->get('version', 0), $action, $details, $reason);
                    $this->addFlash('success', match ($action) {
                        ReviewAction::Validated => 'El registro está validado.', ReviewAction::Rejected => 'El registro está rechazado. El cliente puede corregirlo y reenviarlo.', default => 'El justificante está solicitado. La validación queda pendiente.',
                    });

                    return $this->redirectToRoute('firm_document', ['id' => $id]);
                } catch (DocumentChanged) {
                    $this->addFlash('error', 'El registro ha cambiado. Revisa su versión actual antes de decidir.');

                    return $this->redirectToRoute('firm_document', ['id' => $id]);
                } catch (DocumentNotFound) {
                    throw $this->createNotFoundException();
                } catch (IncompleteDocument $exception) {
                    $fieldErrors = $form->fieldErrors($exception, $document->tax());
                    $error = 'Completa los campos marcados antes de validar.';
                } catch (InvalidTaxRate) {
                    $fieldErrors['taxRate'] = $form->rateError($document->tax());
                    $error = 'Revisa el porcentaje del impuesto.';
                } catch (InvalidSurcharge) {
                    $fieldErrors['surcharge'] = 'El recargo debe corresponder al IVA de un gasto.';
                    $error = 'Revisa el recargo.';
                } catch (InvalidWithholding) {
                    $fieldErrors['withholdingRate'] = 'Solo se admite retención en ingresos de autónomos, entre 0 y 100 y con hasta dos decimales.';
                    $error = 'Revisa la retención.';
                } catch (InvalidDocument) {
                    $error = null !== $document->pendingRequest() ? 'Falta el justificante solicitado. No se puede validar todavía.' : 'Comprueba el estado y los datos del registro. Para rechazar o pedir justificante, escribe un motivo entre 1 y 1000 caracteres.';
                }
            }
        }
        $view = $form->viewDocument($document, $input, $withholdingPolicy->allows($document->clientUserId()));
        $view['editable'] = DocumentStatus::Submitted === $document->status() && null === $document->pendingRequest();

        return $this->render('firm/document.html.twig', ['document' => $view, 'clientName' => $entry->clientName, 'history' => $presenter->history($document), 'supportingFiles' => $presenter->files($document), 'fieldErrors' => $fieldErrors, 'error' => $error, 'reason' => $reason, 'reviewer' => true, 'csrfId' => 'review_document_'.$id]);
    }

    #[Route('/bandeja/{id}/archivo', name: 'firm_document_file', methods: ['GET'], requirements: ['id' => self::ID])]
    public function original(string $id, FirmDocumentAccess $access, DocumentFileStore $files): Response
    {
        try {
            $document = $access->open($this->actor()->id(), $id)->document;

            return DocumentFileResponse::create($files->read($document->storedPath()), $document->mediaType(), $document->originalName());
        } catch (DocumentNotFound) {
            throw $this->createNotFoundException();
        }
    }

    #[Route('/bandeja/{id}/justificantes/{fileId}', name: 'firm_supporting_file', methods: ['GET'], requirements: ['id' => self::ID, 'fileId' => self::ID])]
    public function supporting(string $id, string $fileId, ReadSupportingDocument $read, DocumentFileStore $files): Response
    {
        try {
            $file = $read->forFirm($this->actor()->id(), $id, $fileId);

            return DocumentFileResponse::create($files->read($file->storedPath), $file->mediaType, $file->originalName);
        } catch (DocumentNotFound) {
            throw $this->createNotFoundException();
        }
    }

    private function actor(): SecurityUser
    {
        $user = $this->getUser();
        if (!$user instanceof SecurityUser || !in_array($user->role(), [Role::FirmAdmin, Role::Advisor], true)) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
