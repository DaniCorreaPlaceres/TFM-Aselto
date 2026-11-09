<?php

namespace App\Comunicacion\Infrastructure\Storage;

use App\Comunicacion\Domain\AttachmentStore;
use App\Comunicacion\Domain\ConversationNotFound;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class LocalAttachmentStore implements AttachmentStore
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir)
    {
    }

    public function write(string $id, string $extension, string $contents): string
    {
        if (1 !== preg_match('/^[0-9a-f-]{36}$/', $id) || !in_array($extension, ['pdf', 'jpg', 'png', 'webp'], true)) {
            throw new \InvalidArgumentException('Invalid attachment path.');
        }
        $directory = $this->projectDir.'/var/storage/messages';
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('The attachment directory could not be created.');
        }
        $path = $id.'.'.$extension;
        if (strlen($contents) !== file_put_contents($directory.'/'.$path, $contents, LOCK_EX)) {
            @unlink($directory.'/'.$path);
            throw new \RuntimeException('The attachment could not be stored.');
        }

        return $path;
    }

    public function read(string $path): string
    {
        $contents = @file_get_contents($this->absolute($path));
        if (false === $contents) {
            throw new ConversationNotFound('The attachment is not available.');
        }

        return $contents;
    }

    public function remove(string $path): void
    {
        $absolute = $this->absolute($path);
        if (!unlink($absolute)) {
            throw new \RuntimeException('The unused attachment could not be removed.');
        }
    }

    private function absolute(string $path): string
    {
        if (1 !== preg_match('/^[0-9a-f-]{36}\.(pdf|jpg|png|webp)$/', $path)) {
            throw new ConversationNotFound('The attachment is not available.');
        }
        $directory = realpath($this->projectDir.'/var/storage/messages');
        $absolute = false === $directory ? false : realpath($directory.'/'.$path);
        if (false === $directory || false === $absolute || dirname($absolute) !== $directory) {
            throw new ConversationNotFound('The attachment is not available.');
        }

        return $absolute;
    }
}
