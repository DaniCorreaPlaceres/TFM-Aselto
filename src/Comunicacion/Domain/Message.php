<?php

namespace App\Comunicacion\Domain;

final class Message
{
    public readonly string $text;

    /** @param list<Attachment> $attachments */
    public function __construct(public readonly string $actorId, public readonly \DateTimeImmutable $at, string $text, public readonly array $attachments = [])
    {
        $this->text = trim($text);
        if ('' === $this->text || mb_strlen($this->text) > 4000 || count($attachments) > 3) {
            throw new InvalidMessage('Escribe un mensaje de 1 a 4000 caracteres y adjunta como máximo tres archivos.');
        }
    }
}
