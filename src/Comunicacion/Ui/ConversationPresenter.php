<?php

namespace App\Comunicacion\Ui;

use App\Cliente\Domain\ClientRepository;
use App\Comunicacion\Domain\Conversation;
use App\Identidad\Domain\UserRepository;

final class ConversationPresenter
{
    public function __construct(private readonly UserRepository $users, private readonly ClientRepository $clients)
    {
    }

    /** @return array<string, mixed> */
    public function view(Conversation $conversation, string $actorId): array
    {
        return ['id' => $conversation->id, 'subject' => $conversation->subject, 'client' => $this->clients->findByUserId($conversation->ownerId)?->name() ?? 'Cliente', 'request' => $conversation->request, 'resolvedAt' => $conversation->resolvedAt, 'resolvedBy' => null === $conversation->resolvedBy ? null : $this->users->find($conversation->resolvedBy)?->email(), 'unread' => $conversation->unread($actorId), 'documentId' => $conversation->documentId, 'messages' => array_map(fn ($message): array => ['author' => $this->users->find($message->actorId)?->email() ?? 'Autor no disponible', 'at' => $message->at, 'text' => $message->text, 'files' => $message->attachments], $conversation->messages)];
    }
}
