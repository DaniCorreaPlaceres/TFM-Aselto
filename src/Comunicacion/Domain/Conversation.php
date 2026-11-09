<?php

namespace App\Comunicacion\Domain;

final class Conversation
{
    public readonly string $subject;

    /** @var non-empty-list<Message> */
    public readonly array $messages;

    /**
     * @param list<Message>      $messages
     * @param array<string, int> $readers
     */
    public function __construct(public readonly string $id, public readonly string $ownerId, public readonly string $firmId, string $subject, public readonly bool $request, public readonly ?string $documentId, array $messages, public readonly array $readers = [], public readonly ?string $resolvedBy = null, public readonly ?\DateTimeImmutable $resolvedAt = null, public readonly int $version = 1)
    {
        $this->subject = trim($subject);
        if ('' === $this->subject || mb_strlen($this->subject) > 120 || [] === $messages) {
            throw new InvalidMessage('Escribe un asunto de 1 a 120 caracteres y un mensaje.');
        }
        $this->messages = $messages;
    }

    public function reply(Message $message): self
    {
        // Sending does not mark unseen messages from the other party as read.
        return new self($this->id, $this->ownerId, $this->firmId, $this->subject, $this->request, $this->documentId, [...$this->messages, $message], $this->readers, $this->resolvedBy, $this->resolvedAt, $this->version);
    }

    public function read(string $actorId, int $sequence): self
    {
        $readers = $this->readers;
        $readers[$actorId] = max($readers[$actorId] ?? 0, min(count($this->messages), max(0, $sequence)));

        return new self($this->id, $this->ownerId, $this->firmId, $this->subject, $this->request, $this->documentId, $this->messages, $readers, $this->resolvedBy, $this->resolvedAt, $this->version);
    }

    public function resolve(string $actorId, \DateTimeImmutable $at): self
    {
        if (!$this->request || null !== $this->resolvedAt) {
            throw new InvalidMessage('Solo se puede resolver una solicitud que siga pendiente.');
        }

        return new self($this->id, $this->ownerId, $this->firmId, $this->subject, $this->request, $this->documentId, $this->messages, $this->readers, $actorId, $at, $this->version);
    }

    public function unread(string $actorId): int
    {
        $unread = 0;
        foreach ($this->messages as $offset => $message) {
            if ($offset + 1 > ($this->readers[$actorId] ?? 0) && $actorId !== $message->actorId) {
                ++$unread;
            }
        }

        return $unread;
    }
}
