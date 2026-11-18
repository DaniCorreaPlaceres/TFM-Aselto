<?php

namespace App\Shared\Infrastructure\Security;

use App\Shared\Domain\SecurityEntry;
use App\Shared\Domain\SecurityJournal;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

final class PrivateSecurityHandler extends AbstractProcessingHandler
{
    public function __construct(private readonly SecurityJournal $journal)
    {
        parent::__construct(Level::Info, false);
    }

    protected function write(LogRecord $record): void
    {
        $entry = $record->context['entry'] ?? null;
        if (!$entry instanceof SecurityEntry) {
            throw new \InvalidArgumentException('Invalid private audit entry.');
        }
        $this->journal->append($entry);
    }
}
