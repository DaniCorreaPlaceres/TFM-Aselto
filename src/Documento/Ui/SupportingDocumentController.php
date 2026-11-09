<?php

namespace App\Documento\Ui;

use App\Documento\Application\ReadSupportingDocument;
use App\Documento\Application\SupplySupportingDocument;
use App\Documento\Domain\DocumentChanged;
use App\Documento\Domain\DocumentFileStore;
use App\Documento\Domain\DocumentNotFound;
use App\Documento\Domain\InvalidDocument;
use App\Documento\Domain\UnsupportedFile;
use App\Identidad\Domain\Role;
use App\Identidad\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SupportingDocumentController extends AbstractController
{
    private const ID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    #[Route('/documentos/{id}/justificantes', name: 'client_document_support', methods: ['POST'], requirements: ['id' => self::ID])]
    public function supply(string $id, Request $request, SupplySupportingDocument $supply): Response
    {
        $user = $this->client();
        if (!$this->isCsrfTokenValid('support_document_'.$id, (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'La sesión del formulario ha caducado. Vuelve a intentarlo.');
        } else {
            try {
                $file = $request->files->get('file');
                if (!$file instanceof UploadedFile || !$file->isValid()) {
                    throw new UnsupportedFile('A file is required.');
                }
                $supply->execute($user->id(), $id, (int) $request->request->get('version', 0), $file->getClientOriginalName(), (string) $file->getMimeType(), (string) file_get_contents($file->getPathname()));
                $this->addFlash('success', 'El justificante está aportado. Tu asesoría lo revisará.');
            } catch (DocumentNotFound) {
                throw $this->createNotFoundException();
            } catch (DocumentChanged) {
                $this->addFlash('error', 'El registro ha cambiado. Revisa su versión actual antes de aportar el justificante.');
            } catch (UnsupportedFile) {
                $this->addFlash('error', 'El archivo debe ser PDF, JPEG, PNG o WebP, y ocupar hasta 8 MB.');
            } catch (InvalidDocument) {
                $this->addFlash('error', 'No hay una solicitud de justificante pendiente para este registro.');
            }
        }

        return $this->redirectToRoute('client_document', ['id' => $id]);
    }

    #[Route('/documentos/{id}/justificantes/{fileId}', name: 'client_supporting_file', methods: ['GET'], requirements: ['id' => self::ID, 'fileId' => self::ID])]
    public function download(string $id, string $fileId, ReadSupportingDocument $read, DocumentFileStore $files): Response
    {
        try {
            $file = $read->forOwner($this->client()->id(), $id, $fileId);

            return DocumentFileResponse::create($files->read($file->storedPath), $file->mediaType, $file->originalName);
        } catch (DocumentNotFound) {
            throw $this->createNotFoundException();
        }
    }

    private function client(): SecurityUser
    {
        $user = $this->getUser();
        if (!$user instanceof SecurityUser || Role::Client !== $user->role()) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
