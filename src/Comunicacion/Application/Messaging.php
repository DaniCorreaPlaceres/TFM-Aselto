<?php

namespace App\Comunicacion\Application;

use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientRepository;
use App\Comunicacion\Domain\Attachment;
use App\Comunicacion\Domain\AttachmentStore;
use App\Comunicacion\Domain\Conversation;
use App\Comunicacion\Domain\ConversationNotFound;
use App\Comunicacion\Domain\ConversationRepository;
use App\Comunicacion\Domain\InvalidMessage;
use App\Comunicacion\Domain\Message;
use App\Documento\Domain\DocumentRepository;
use App\Documento\Domain\DocumentStatus;
use App\Identidad\Domain\Role;
use App\Shared\Domain\ClientTransaction;
use App\Shared\Domain\Clock;
use App\Shared\Domain\IdGenerator;

final class Messaging
{
    public function __construct(private readonly ConversationAccess $access, private readonly ConversationRepository $conversations, private readonly ClientRepository $clients, private readonly DocumentRepository $documents, private readonly AttachmentStore $files, private readonly ClientTransaction $transaction, private readonly IdGenerator $ids, private readonly Clock $clock)
    {
    }

    /** @return list<Client> */
    public function recipients(string $actorId): array
    {
        $actor = $this->access->actor($actorId);
        if (Role::Client === $actor->role()) {
            return null === $actor->firmId() ? [] : [$this->access->client($actorId, $actorId)];
        }

        return null === $actor->firmId() ? [] : $this->clients->findByFirm($actor->firmId());
    }

    /** @return list<Conversation> */
    public function list(string $actorId): array
    {
        $actor = $this->access->actor($actorId);
        $records = Role::Client === $actor->role() ? $this->conversations->forOwner($actorId) : (null === $actor->firmId() ? [] : $this->conversations->forFirm($actor->firmId()));
        $visible = [];
        foreach ($records as $record) {
            try {
                $this->access->assertConversation($actorId, $record);
            } catch (ConversationNotFound) {
                continue;
            }
            $visible[] = $record;
        }
        usort($visible, static fn (Conversation $a, Conversation $b): int => $b->messages[array_key_last($b->messages)]->at <=> $a->messages[array_key_last($a->messages)]->at);

        return $visible;
    }

    /** @return list<\App\Documento\Domain\Document> */
    public function sharedDocuments(string $actorId, string $ownerId): array
    {
        $client = $this->access->client($actorId, $ownerId);

        return array_values(array_filter($this->documents->findByOwner($ownerId), static fn ($document): bool => $document->firmId() === $client->firmId() && in_array($document->status(), [DocumentStatus::Submitted, DocumentStatus::Rejected, DocumentStatus::Validated], true)));
    }

    public function open(string $actorId, string $id): Conversation
    {
        $shown = $this->authorized($actorId, $id);
        $sequence = count($shown->messages);
        $this->transaction->run($shown->ownerId, function () use ($actorId, $id, $sequence): void {
            $current = $this->authorized($actorId, $id);
            if (($current->readers[$actorId] ?? 0) < $sequence) {
                $this->conversations->save($current->read($actorId, $sequence));
            }
        });

        // Keep the displayed snapshot: later messages must remain unread.
        return $shown;
    }

    /** @param list<array{name: string, contents: string}> $uploads */
    public function start(string $actorId, string $ownerId, string $subject, string $text, bool $request = false, ?string $documentId = null, array $uploads = []): Conversation
    {
        return $this->write($actorId, $ownerId, $text, $uploads, function (Message $message) use ($actorId, $ownerId, $subject, $request, $documentId): Conversation {
            $client = $this->access->client($actorId, $ownerId);
            if ($request) {
                $this->access->assertStaff($actorId);
            }
            $documentId = '' === $documentId ? null : $documentId;
            if (null !== $documentId) {
                $document = $this->documents->find($documentId);
                if (null === $document || $document->clientUserId() !== $ownerId || $document->firmId() !== $client->firmId() || !in_array($document->status(), [DocumentStatus::Submitted, DocumentStatus::Rejected, DocumentStatus::Validated], true)) {
                    throw new InvalidMessage('El documento no está compartido con esta asesoría y este cliente.');
                }
            }

            return new Conversation($this->ids->next(), $ownerId, (string) $client->firmId(), $subject, $request, $documentId, [$message]);
        });
    }

    /** @param list<array{name: string, contents: string}> $uploads */
    public function reply(string $actorId, string $id, string $text, array $uploads = []): Conversation
    {
        $existing = $this->authorized($actorId, $id);

        return $this->write($actorId, $existing->ownerId, $text, $uploads, fn (Message $message): Conversation => $this->authorized($actorId, $id)->reply($message));
    }

    public function resolve(string $actorId, string $id): void
    {
        $existing = $this->authorized($actorId, $id);
        $this->transaction->run($existing->ownerId, function () use ($actorId, $id): void {
            $current = $this->authorized($actorId, $id);
            $this->access->assertStaff($actorId);
            $this->conversations->save($current->resolve($actorId, $this->clock->now()));
        });
    }

    /** @return array{file: Attachment, contents: string} */
    public function attachment(string $actorId, string $id, string $fileId): array
    {
        $conversation = $this->authorized($actorId, $id);
        foreach ($conversation->messages as $message) {
            foreach ($message->attachments as $file) {
                if ($file->id === $fileId) {
                    return ['file' => $file, 'contents' => $this->files->read($file->path)];
                }
            }
        }
        throw new ConversationNotFound('The attachment is not available.');
    }

    private function authorized(string $actorId, string $id): Conversation
    {
        $conversation = $this->conversations->find($id);
        if (null === $conversation) {
            throw new ConversationNotFound('The conversation is not available.');
        }
        $this->access->assertConversation($actorId, $conversation);

        return $conversation;
    }

    /**
     * @param list<array{name: string, contents: string}> $uploads
     * @param callable(Message): Conversation             $build
     */
    private function write(string $actorId, string $ownerId, string $text, array $uploads, callable $build): Conversation
    {
        $this->access->client($actorId, $ownerId);
        new Message($actorId, $this->clock->now(), $text);
        if (count($uploads) > 3) {
            throw new InvalidMessage('Puedes adjuntar hasta tres archivos por mensaje.');
        }
        $checked = [];
        foreach ($uploads as $upload) {
            $checked[] = [...MessageUpload::read($upload['name'], $upload['contents']), 'contents' => $upload['contents']];
        }
        $paths = [];
        try {
            return $this->transaction->run($ownerId, function () use ($actorId, $ownerId, $text, $checked, $build, &$paths): Conversation {
                $this->access->client($actorId, $ownerId);
                $attachments = [];
                foreach ($checked as $upload) {
                    $id = $this->ids->next();
                    $path = $this->files->write($id, $upload['extension'], $upload['contents']);
                    $paths[] = $path;
                    $attachments[] = new Attachment($id, $upload['name'], $path, $upload['type']);
                }
                $conversation = $build(new Message($actorId, $this->clock->now(), $text, $attachments));
                $this->conversations->save($conversation);

                return $conversation;
            });
        } catch (\Throwable $error) {
            foreach ($paths as $path) {
                $this->files->remove($path);
            }
            throw $error;
        }
    }
}
