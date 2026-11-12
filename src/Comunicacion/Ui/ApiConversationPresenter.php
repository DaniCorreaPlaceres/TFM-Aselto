<?php

namespace App\Comunicacion\Ui;

use App\Comunicacion\Domain\Conversation;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ApiConversationPresenter
{
    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    /** @return array<string, mixed> */
    public function view(Conversation $conversation, string $actorId): array
    {
        return [
            'id' => $conversation->id,
            'owner_id' => $conversation->ownerId,
            'firm_id' => $conversation->firmId,
            'subject' => $conversation->subject,
            'request' => $conversation->request,
            'document_id' => $conversation->documentId,
            'resolved_by' => $conversation->resolvedBy,
            'resolved_at' => $conversation->resolvedAt?->format(DATE_ATOM),
            'unread' => $conversation->unread($actorId),
            'messages' => array_map(fn ($message): array => [
                'actor_id' => $message->actorId,
                'at' => $message->at->format(DATE_ATOM),
                'text' => $message->text,
                'attachments' => array_map(fn ($file): array => ['id' => $file->id, 'name' => $file->name, 'media_type' => $file->mediaType, 'url' => $this->urls->generate('api_conversation_attachment', ['id' => $conversation->id, 'fileId' => $file->id])], $message->attachments),
            ], $conversation->messages),
        ];
    }
}
