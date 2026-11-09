<?php

namespace App\Documento\Domain;

final class ReviewRecord
{
    public function __construct(
        public readonly ReviewAction $action,
        public readonly string $actorId,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly ?string $reason,
        public readonly DocumentDetails $before,
        public readonly ?DocumentDetails $after = null,
        public readonly ?string $supportingFileId = null,
    ) {
        if (1 !== preg_match('/^[0-9a-f-]{36}$/', $actorId)) {
            throw new InvalidDocument('The review actor id is not valid.');
        }

        if (in_array($action, [ReviewAction::Rejected, ReviewAction::Requested], true) && (null === $reason || '' === trim($reason) || mb_strlen($reason) > 1000)) {
            throw new InvalidDocument('A reason between 1 and 1000 characters is required.');
        }
    }
}
