<?php

namespace App\Comunicacion\Infrastructure\Persistence;

use App\Comunicacion\Domain\Attachment;
use App\Comunicacion\Domain\Conversation;
use App\Comunicacion\Domain\Message;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'client_conversation')]
#[ORM\Index(name: 'conversation_owner', columns: ['owner_id'])]
#[ORM\Index(name: 'conversation_firm', columns: ['firm_id'])]
class DoctrineConversation
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $id;
    #[ORM\Column(length: 36)]
    private string $ownerId;
    #[ORM\Column(length: 36)]
    private string $firmId;
    #[ORM\Column(length: 120)]
    private string $subject;
    #[ORM\Column]
    private bool $request;
    #[ORM\Column(length: 36, nullable: true)]
    private ?string $documentId;
    /** @var non-empty-list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $messages;
    /** @var array<string, int> */
    #[ORM\Column(type: 'json')]
    private array $readers;
    #[ORM\Column(length: 36, nullable: true)]
    private ?string $resolvedBy;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resolvedAt;
    #[ORM\Version]
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    public function apply(Conversation $conversation): void
    {
        $this->id = $conversation->id;
        $this->ownerId = $conversation->ownerId;
        $this->firmId = $conversation->firmId;
        $this->subject = $conversation->subject;
        $this->request = $conversation->request;
        $this->documentId = $conversation->documentId;
        $this->messages = array_map(static fn (Message $message): array => ['actor' => $message->actorId, 'at' => $message->at->format('Y-m-d\TH:i:s.uP'), 'text' => $message->text, 'files' => array_map(static fn (Attachment $file): array => get_object_vars($file), $message->attachments)], $conversation->messages);
        $this->readers = $conversation->readers;
        $this->resolvedBy = $conversation->resolvedBy;
        $this->resolvedAt = $conversation->resolvedAt;
    }

    public function toDomain(): Conversation
    {
        $messages = array_map(static fn (array $message): Message => new Message((string) $message['actor'], new \DateTimeImmutable((string) $message['at']), (string) $message['text'], array_map(static fn (array $file): Attachment => new Attachment($file['id'], $file['name'], $file['path'], $file['mediaType']), $message['files'])), $this->messages);

        return new Conversation($this->id, $this->ownerId, $this->firmId, $this->subject, $this->request, $this->documentId, $messages, $this->readers, $this->resolvedBy, $this->resolvedAt, $this->version);
    }
}
