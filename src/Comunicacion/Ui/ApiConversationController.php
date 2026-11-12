<?php

namespace App\Comunicacion\Ui;

use App\Comunicacion\Application\Messaging;
use App\Identidad\Domain\Role;
use App\Identidad\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(requirements: ['id' => ApiConversationInput::UUID, 'fileId' => ApiConversationInput::UUID, 'ownerId' => ApiConversationInput::UUID])]
final class ApiConversationController extends AbstractController
{
    public function __construct(private readonly Messaging $messaging, private readonly ApiConversationInput $input, private readonly ApiConversationPresenter $presenter)
    {
    }

    #[Route('/api/v1/conversation-recipients', name: 'api_conversation_recipients', methods: ['GET'])]
    public function recipients(): JsonResponse
    {
        $actor = $this->actor();

        return $this->response(['recipients' => array_map(static fn ($client): array => ['client_id' => $client->id(), 'owner_id' => $client->userId(), 'name' => $client->name()], $this->messaging->recipients($actor->id()))]);
    }

    #[Route('/api/v1/conversation-recipients/{ownerId}/documents', name: 'api_conversation_documents', methods: ['GET'])]
    public function documents(string $ownerId): JsonResponse
    {
        $actor = $this->actor();

        return $this->response(['documents' => array_map(static fn ($document): array => ['id' => $document->id(), 'name' => $document->originalName(), 'status' => $document->status()->value], $this->messaging->sharedDocuments($actor->id(), $ownerId))]);
    }

    #[Route('/api/v1/conversations', name: 'api_conversations', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $actor = $this->actor();

        return $this->response(['conversations' => array_map(fn ($conversation): array => $this->presenter->view($conversation, $actor->id()), $this->messaging->list($actor->id()))]);
    }

    #[Route('/api/v1/conversations', name: 'api_conversation_start', methods: ['POST'])]
    public function start(Request $request): JsonResponse
    {
        $actor = $this->actor();
        $data = $this->input->data($request, true);
        $conversation = $this->messaging->start($actor->id(), $data['owner_id'], $data['subject'], $data['text'], $data['request'] ?? false, $data['document_id'] ?? null, $this->input->uploads($request));
        $response = $this->response($this->presenter->view($conversation, $actor->id()), 201);
        $response->headers->set('Location', $this->generateUrl('api_conversation', ['id' => $conversation->id]));

        return $response;
    }

    #[Route('/api/v1/conversations/{id}', name: 'api_conversation', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $actor = $this->actor();
        $shown = $this->messaging->open($actor->id(), $id);

        // Only the displayed sequence is marked read, preserving later concurrent messages.
        return $this->response($this->presenter->view($shown->read($actor->id(), count($shown->messages)), $actor->id()));
    }

    #[Route('/api/v1/conversations/{id}/messages', name: 'api_conversation_reply', methods: ['POST'])]
    public function reply(string $id, Request $request): JsonResponse
    {
        $actor = $this->actor();
        $data = $this->input->data($request, false);
        $conversation = $this->messaging->reply($actor->id(), $id, $data['text'], $this->input->uploads($request));

        return $this->response($this->presenter->view($conversation, $actor->id()));
    }

    #[Route('/api/v1/conversations/{id}/resolve', name: 'api_conversation_resolve', methods: ['POST'])]
    public function resolve(string $id, Request $request): Response
    {
        $actor = $this->actor();
        $this->input->data($request, false, true);
        if ([] !== $request->files->all()) {
            throw new BadRequestHttpException();
        }
        $this->messaging->resolve($actor->id(), $id);

        return new Response(status: 204, headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/v1/conversations/{id}/attachments/{fileId}', name: 'api_conversation_attachment', methods: ['GET'])]
    public function attachment(string $id, string $fileId): Response
    {
        $actor = $this->actor();
        $download = $this->messaging->attachment($actor->id(), $id, $fileId);
        $file = $download['file'];
        $fallback = preg_replace('/[^\x20-\x7e]/', '_', str_replace('%', '_', $file->name)) ?: 'adjunto';

        return new Response($download['contents'], headers: ['Content-Type' => $file->mediaType, 'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $file->name, $fallback), 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function actor(): SecurityUser
    {
        $actor = $this->getUser();
        if (!$actor instanceof SecurityUser || !in_array($actor->role(), [Role::Client, Role::FirmAdmin, Role::Advisor], true)) {
            throw $this->createAccessDeniedException();
        }

        return $actor;
    }

    /** @param array<string, mixed> $data */
    private function response(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status, ['Cache-Control' => 'private, no-store']);
    }
}
