<?php

namespace App\Documento\Infrastructure\Storage;

use App\Documento\Domain\DocumentFileStore;
use App\Documento\Domain\DocumentNotFound;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class LocalDocumentFileStore implements DocumentFileStore
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function write(string $documentId, string $extension, string $contents): string
    {
        $directory = $this->directory().\DIRECTORY_SEPARATOR.'documents';

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('The document directory could not be created.');
        }

        $relativePath = 'documents/'.$documentId.'.'.$extension;
        $written = file_put_contents($directory.\DIRECTORY_SEPARATOR.$documentId.'.'.$extension, $contents);

        if (false === $written) {
            throw new \RuntimeException('The document could not be stored.');
        }

        return $relativePath;
    }

    public function read(string $relativePath): string
    {
        $path = $this->absolute($relativePath);
        $contents = file_get_contents($path);

        if (false === $contents) {
            throw new DocumentNotFound('The file does not exist.');
        }

        return $contents;
    }

    private function absolute(string $relativePath): string
    {
        $directory = rtrim(str_replace('\\', '/', $this->directory()), '/').'/';
        $path = realpath($directory.ltrim($relativePath, '/'));

        if (false === $path || !str_starts_with(str_replace('\\', '/', $path), $directory)) {
            throw new DocumentNotFound('The file does not exist.');
        }

        return $path;
    }

    private function directory(): string
    {
        $directory = $this->projectDir.'/var/storage';
        $real = realpath($directory);

        return false === $real ? $directory : $real;
    }
}
