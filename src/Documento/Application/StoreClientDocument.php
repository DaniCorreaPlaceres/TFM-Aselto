<?php

namespace App\Documento\Application;

use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\ClientRepository;
use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentExtractor;
use App\Documento\Domain\DocumentFileStore;
use App\Documento\Domain\DocumentRepository;
use App\Documento\Domain\ExtractionFailed;
use App\Documento\Domain\MissingClientProfile;
use App\Documento\Domain\Tax;
use App\Shared\Domain\Clock;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\Transaction;

final class StoreClientDocument
{
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

        $tax = Tax::forProvince($client->contact()->province());

        $upload = DocumentUpload::read($originalName, $mediaType, $contents);
        $storedType = $upload['mediaType'];
        $id = $this->ids->next();
        $path = $this->files->write($id, $upload['extension'], $contents);
        $document = Document::receive(
            $id,
            $client->userId(),
            $client->firmId(),
            $upload['originalName'],
            $path,
            $storedType,
            $this->clock->now(),
            $tax,
        );

        try {
            $allowsWithholding = ClientKind::SelfEmployed === $client->kind();
            $proposal = $this->extractor->propose($storedType, $contents, $tax, $allowsWithholding);
            if (!$allowsWithholding && null !== $proposal->details()->withholdingRate()) {
                throw new ExtractionFailed('Withholding is not available for this client.');
            }
            $document = $document->propose($proposal->details(), $proposal->origin());
        } catch (ExtractionFailed) {
        }

        $this->transaction->run(function () use ($document): void {
            $this->documents->save($document);
        });

        return $document;
    }
}
