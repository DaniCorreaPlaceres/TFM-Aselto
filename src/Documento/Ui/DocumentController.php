<?php

namespace App\Documento\Ui;

use App\Cliente\Application\ShowClientHome;
use App\Documento\Application\ConfirmDocument;
use App\Documento\Application\DocumentWithholdingPolicy;
use App\Documento\Application\ListClientDocuments;
use App\Documento\Application\OpenClientDocument;
use App\Documento\Application\SaveDocumentDraft;
use App\Documento\Application\StoreClientDocument;
use App\Documento\Domain\DocumentChanged;
use App\Documento\Domain\DocumentFileStore;
use App\Documento\Domain\DocumentNotFound;
use App\Documento\Domain\IncompleteDocument;
use App\Documento\Domain\InvalidDocument;
use App\Documento\Domain\InvalidSurcharge;
use App\Documento\Domain\InvalidTaxRate;
use App\Documento\Domain\InvalidWithholding;
use App\Documento\Domain\MissingClientProfile;
use App\Documento\Domain\MissingFiscalProvince;
use App\Documento\Domain\ProposalOrigin;
use App\Documento\Domain\UnsupportedFile;
use App\Identidad\Domain\Role;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ClosedPeriod;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
                'status' => DocumentLabel::forDocument($document),
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
        DocumentReviewForm $form,
        DocumentReviewPresenter $presenter,
        \App\Cliente\Domain\ClientRepository $clients,
    ): Response {
        $user = $this->client();

        try {
            $document = $openClientDocument->execute($user->id(), $id);
        } catch (DocumentNotFound) {
            throw $this->createNotFoundException();
        }

        $fieldErrors = [];
        $input = $form->inputFromDocument($document);

        if ($request->isMethod('POST') && $document->isEditable()) {
            $input = $form->inputFromRequest($request);

            if (!$this->isCsrfTokenValid('save_document', (string) $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'La sesión del formulario ha caducado. Vuelve a intentarlo.');
            } else {
                try {
                    $document->assertVersion((int) $request->request->get('version', 0));
                    $saved = 'confirm' === $request->request->get('intent')
                        ? $confirmDocument->execute($user->id(), $id, ...$form->arguments($input), expectedVersion: (int) $request->request->get('version', 0))
                        : $saveDocumentDraft->execute($user->id(), $id, ...$form->arguments($input), expectedVersion: (int) $request->request->get('version', 0));
                    $this->addFlash('success', $saved->isEditable()
                        ? 'El borrador está guardado.'
                        : 'Has confirmado el registro.');

                    return $this->redirectToRoute('client_document', ['id' => $id]);
                } catch (ClosedPeriod) {
                    $this->addFlash('error', 'El trimestre está cerrado. Reábrelo antes de modificar sus registros.');
                } catch (DocumentChanged) {
                    $this->addFlash('error', 'El registro ha cambiado. Revisa su versión actual antes de guardar.');

                    return $this->redirectToRoute('client_document', ['id' => $id]);
                } catch (IncompleteDocument $exception) {
                    $fieldErrors = $form->fieldErrors($exception, $document->tax());
                    $this->addFlash('error', 'Revisa los campos marcados.');
                } catch (InvalidWithholding) {
                    $fieldErrors['withholdingRate'] = 'La retención solo se admite en ingresos de autónomos. Escribe un porcentaje entre 0 y 100, con hasta dos decimales, o deja el campo vacío.';
                    $this->addFlash('error', 'Revisa la retención de IRPF.');
                } catch (InvalidSurcharge) {
                    $fieldErrors['surcharge'] = 'El recargo solo se admite en gastos con IVA y debe corresponder con su tipo. Revisa la selección.';
                    $this->addFlash('error', 'Revisa el recargo de equivalencia.');
                } catch (InvalidTaxRate) {
                    $fieldErrors['taxRate'] = $form->rateError($document->tax());
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
            'document' => $form->viewDocument($document, $input, $withholdingPolicy->allows($user->id())),
            'history' => $presenter->history($document),
            'supportingFiles' => $presenter->files($document),
            'fieldErrors' => $fieldErrors,
            'hasFirm' => null !== $document->firmId(),
            'canAmendPersonal' => null === $clients->findByUserId($user->id())?->firmId() && null === $document->firmId() && \App\Documento\Domain\DocumentStatus::Submitted === $document->status(),
        ]);
    }

    #[Route('/documentos/{id}/corregir', name: 'client_document_amend', methods: ['POST'], requirements: ['id' => self::ID])]
    public function amend(string $id, Request $request, \App\Documento\Application\AmendPersonalDocument $amend): Response
    {
        $user = $this->client();
        if (!$this->isCsrfTokenValid('amend_document_'.$id, (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'La sesión del formulario ha caducado. Vuelve a intentarlo.');
        } else {
            try {
                $amend->execute($user->id(), $id, (int) $request->request->get('version', 0));
                $this->addFlash('success', 'Ya puedes corregir el registro. Confírmalo de nuevo para incluirlo en tus cifras.');
            } catch (ClosedPeriod) {
                $this->addFlash('error', 'El trimestre está cerrado. Reábrelo antes de corregir el registro.');
            } catch (DocumentChanged|InvalidDocument) {
                $this->addFlash('error', 'El registro ha cambiado. Revisa su estado actual.');
            } catch (DocumentNotFound) {
                throw $this->createNotFoundException();
            }
        }

        return $this->redirectToRoute('client_document', ['id' => $id]);
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

        return DocumentFileResponse::create($contents, $document->mediaType(), $document->originalName());
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
}
