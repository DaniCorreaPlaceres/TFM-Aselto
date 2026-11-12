<?php

namespace App\Documento\Ui;

use App\Documento\Application\AmendPersonalDocument;
use App\Documento\Application\ConfirmDocument;
use App\Documento\Application\FirmDocumentAccess;
use App\Documento\Application\ListClientDocuments;
use App\Documento\Application\ListFirmDocuments;
use App\Documento\Application\OpenClientDocument;
use App\Documento\Application\ReadSupportingDocument;
use App\Documento\Application\ReviewSubmittedDocument;
use App\Documento\Application\SaveDocumentDraft;
use App\Documento\Application\StoreClientDocument;
use App\Documento\Application\SupplySupportingDocument;
use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentFileStore;
use App\Documento\Domain\ReviewAction;
use App\Identidad\Domain\Role;
use App\Identidad\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiDocumentController extends AbstractController
{
    private const ID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    public function __construct(private readonly OpenClientDocument $owners, private readonly FirmDocumentAccess $advisors, private readonly ApiDocumentInput $input, private readonly ApiDocumentPresenter $presenter)
    {
    }

    #[Route('/api/v1/documents', name: 'api_documents', methods: ['GET'])]
    #[Route('/api/v1/advisory/documents', name: 'api_advisory_documents', methods: ['GET'])]
    public function index(Request $request, ListClientDocuments $clients, ListFirmDocuments $firms): JsonResponse
    {
        $actor = $this->actor($request);
        $advisory = $this->advisory($request);
        $documents = $advisory ? array_map(static fn ($record): Document => $record->document, $firms->execute($actor->id())) : $clients->execute($actor->id());

        return $this->jsonResponse(['documents' => array_map(fn (Document $document): array => $this->presenter->view($document, $advisory), $documents)]);
    }

    #[Route('/api/v1/documents', name: 'api_document_upload', methods: ['POST'])]
    public function upload(Request $request, StoreClientDocument $store): JsonResponse
    {
        $actor = $this->actor($request);
        $upload = $this->input->upload($request);
        $document = $store->execute($actor->id(), $upload['name'], $upload['media_type'], $upload['contents']);

        return $this->documentResponse($request, $actor, $document->id(), 201);
    }

    #[Route('/api/v1/documents/{id}', name: 'api_document', methods: ['GET'], requirements: ['id' => self::ID])]
    #[Route('/api/v1/advisory/documents/{id}', name: 'api_advisory_document', methods: ['GET'], requirements: ['id' => self::ID])]
    public function show(string $id, Request $request): JsonResponse
    {
        return $this->documentResponse($request, $this->actor($request), $id);
    }

    #[Route('/api/v1/documents/{id}/draft', name: 'api_document_draft', methods: ['PUT'], requirements: ['id' => self::ID])]
    #[Route('/api/v1/documents/{id}/confirm', name: 'api_document_confirm', methods: ['POST'], requirements: ['id' => self::ID])]
    public function edit(string $id, Request $request, SaveDocumentDraft $save, ConfirmDocument $confirm): JsonResponse
    {
        $actor = $this->actor($request);
        $this->owners->execute($actor->id(), $id);
        $data = $this->input->json($request, ApiDocumentInput::FIELDS);
        $arguments = $this->input->arguments($data);
        if ('api_document_confirm' === $request->attributes->get('_route')) {
            $confirm->execute($actor->id(), $id, ...$arguments, expectedVersion: $this->input->version($data));
        } else {
            $save->execute($actor->id(), $id, ...$arguments, expectedVersion: $this->input->version($data));
        }

        return $this->documentResponse($request, $actor, $id);
    }

    #[Route('/api/v1/documents/{id}/amend', name: 'api_document_amend', methods: ['POST'], requirements: ['id' => self::ID])]
    public function amend(string $id, Request $request, AmendPersonalDocument $amend): JsonResponse
    {
        $actor = $this->actor($request);
        $this->owners->execute($actor->id(), $id);
        $data = $this->input->json($request, ['version']);
        $amend->execute($actor->id(), $id, $this->input->version($data));

        return $this->documentResponse($request, $actor, $id);
    }

    #[Route('/api/v1/advisory/documents/{id}/validate', name: 'api_advisory_document_validate', methods: ['POST'], requirements: ['id' => self::ID])]
    #[Route('/api/v1/advisory/documents/{id}/reject', name: 'api_advisory_document_reject', methods: ['POST'], requirements: ['id' => self::ID])]
    #[Route('/api/v1/advisory/documents/{id}/request-supporting-file', name: 'api_advisory_document_request', methods: ['POST'], requirements: ['id' => self::ID])]
    public function review(string $id, Request $request, ReviewSubmittedDocument $review): JsonResponse
    {
        $actor = $this->actor($request);
        $document = $this->advisors->open($actor->id(), $id)->document;
        $action = match ($request->attributes->get('_route')) {
            'api_advisory_document_validate' => ReviewAction::Validated, 'api_advisory_document_reject' => ReviewAction::Rejected, default => ReviewAction::Requested,
        };
        $data = $this->input->json($request, ReviewAction::Validated === $action ? ApiDocumentInput::FIELDS : ['version', 'reason']);
        $review->execute($actor->id(), $id, $this->input->version($data), $action, ReviewAction::Validated === $action ? $this->input->details($data, $document->tax()) : null, $data['reason'] ?? '');

        return $this->documentResponse($request, $actor, $id);
    }

    #[Route('/api/v1/documents/{id}/supporting-files', name: 'api_document_supply', methods: ['POST'], requirements: ['id' => self::ID])]
    public function supply(string $id, Request $request, SupplySupportingDocument $supply): JsonResponse
    {
        $actor = $this->actor($request);
        $this->owners->execute($actor->id(), $id);
        $upload = $this->input->upload($request, true);
        $updated = $supply->execute($actor->id(), $id, (int) $upload['version'], $upload['name'], $upload['media_type'], $upload['contents']);
        $files = $updated->supportingFiles();
        $file = $files[array_key_last($files)];
        $response = $this->documentResponse($request, $actor, $id, 201);
        $response->headers->set('Location', $this->generateUrl('api_document_supporting_file', ['id' => $id, 'fileId' => $file->id]));

        return $response;
    }

    #[Route('/api/v1/documents/{id}/file', name: 'api_document_file', methods: ['GET'], requirements: ['id' => self::ID])]
    #[Route('/api/v1/advisory/documents/{id}/file', name: 'api_advisory_document_file', methods: ['GET'], requirements: ['id' => self::ID])]
    public function original(string $id, Request $request, DocumentFileStore $files): Response
    {
        $actor = $this->actor($request);
        $document = $this->advisory($request) ? $this->advisors->open($actor->id(), $id)->document : $this->owners->execute($actor->id(), $id);

        return DocumentFileResponse::create($files->read($document->storedPath()), $document->mediaType(), $document->originalName());
    }

    #[Route('/api/v1/documents/{id}/supporting-files/{fileId}', name: 'api_document_supporting_file', methods: ['GET'], requirements: ['id' => self::ID, 'fileId' => self::ID])]
    #[Route('/api/v1/advisory/documents/{id}/supporting-files/{fileId}', name: 'api_advisory_document_supporting_file', methods: ['GET'], requirements: ['id' => self::ID, 'fileId' => self::ID])]
    public function supporting(string $id, string $fileId, Request $request, ReadSupportingDocument $read, DocumentFileStore $files): Response
    {
        $actor = $this->actor($request);
        $file = $this->advisory($request) ? $read->forFirm($actor->id(), $id, $fileId) : $read->forOwner($actor->id(), $id, $fileId);

        return DocumentFileResponse::create($files->read($file->storedPath), $file->mediaType, $file->originalName);
    }

    private function actor(Request $request): SecurityUser
    {
        $actor = $this->getUser();
        $roles = $this->advisory($request) ? [Role::FirmAdmin, Role::Advisor] : [Role::Client];
        if (!$actor instanceof SecurityUser || !in_array($actor->role(), $roles, true)) {
            throw $this->createAccessDeniedException();
        }

        return $actor;
    }

    private function advisory(Request $request): bool
    {
        return str_starts_with((string) $request->attributes->get('_route'), 'api_advisory_');
    }

    private function documentResponse(Request $request, SecurityUser $actor, string $id, int $status = 200): JsonResponse
    {
        $advisory = $this->advisory($request);
        // Domain mutations return the previous version; always reload the persisted aggregate.
        $document = $advisory ? $this->advisors->open($actor->id(), $id)->document : $this->owners->execute($actor->id(), $id);
        $response = $this->jsonResponse($this->presenter->view($document, $advisory), $status);
        if (201 === $status) {
            $response->headers->set('Location', $this->generateUrl($advisory ? 'api_advisory_document' : 'api_document', ['id' => $id]));
        }

        return $response;
    }

    /** @param array<string, mixed> $data */
    private function jsonResponse(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status, ['Cache-Control' => 'private, no-store']);
    }
}
