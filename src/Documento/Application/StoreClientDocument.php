<?php

namespace App\Documento\Application;

use App\Cliente\Domain\ClientRepository;
use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentExtractor;
use App\Documento\Domain\DocumentFileStore;
use App\Documento\Domain\DocumentRepository;
use App\Documento\Domain\ExtractionFailed;
use App\Documento\Domain\MissingClientProfile;
use App\Documento\Domain\UnsupportedFile;
use App\Shared\Domain\Clock;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\Transaction;

final class StoreClientDocument
{
    private const MAX_BYTES = 8_000_000;

    /** @var array<string, string> */
    private const EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly ClientRepository $clients,
        private readonly DocumentRepository $documents,
        private readonly DocumentFileStore $files,
        private readonly DocumentExtractor $extractor,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly Transaction $transaction,
    ) {
    }

    public function execute(string $userId, string $originalName, string $mediaType, string $contents): Document
    {
        $client = $this->clients->findByUserId($userId);

        if (null === $client) {
            throw new MissingClientProfile('The client profile does not exist.');
        }

        if ('' === $contents || strlen($contents) > self::MAX_BYTES) {
            throw new UnsupportedFile('The file is empty or too large.');
        }

        $mediaType = mb_strtolower(trim($mediaType));

        if (!isset(self::EXTENSIONS[$mediaType])) {
            throw new UnsupportedFile('The file type is not allowed.');
        }

        $storedType = 'image/jpg' === $mediaType ? 'image/jpeg' : $mediaType;
        $id = $this->ids->next();
        $path = $this->files->write($id, self::EXTENSIONS[$mediaType], $contents);
        $document = Document::receive(
            $id,
            $client->userId(),
            $client->firmId(),
            self::fileName($originalName),
            $path,
            $storedType,
            $this->clock->now(),
        );

        try {
            $proposal = $this->extractor->propose($storedType, $contents);
            $document = $document->propose($proposal->details(), $proposal->origin());
        } catch (ExtractionFailed) {
        }

        $this->transaction->run(function () use ($document): void {
            $this->documents->save($document);
        });

        return $document;
    }

    private static function fileName(string $originalName): string
    {
        $name = trim(basename(str_replace('\\', '/', $originalName)));
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';

        if ('' === $name) {
            return 'documento';
        }

        if (mb_strlen($name) > 180) {
            return mb_substr($name, 0, 180);
        }

        return $name;
    }
}
