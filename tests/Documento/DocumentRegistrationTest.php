<?php

namespace App\Tests\Documento;

use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\ClientRepository;
use App\Documento\Application\ConfirmDocument;
use App\Documento\Application\DocumentForm;
use App\Documento\Application\ListClientDocuments;
use App\Documento\Application\OpenClientDocument;
use App\Documento\Application\SaveDocumentDraft;
use App\Documento\Application\StoreClientDocument;
use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentFileStore;
use App\Documento\Domain\DocumentNotFound;
use App\Documento\Domain\DocumentRepository;
use App\Documento\Domain\DocumentStatus;
use App\Documento\Domain\IncompleteDocument;
use App\Documento\Domain\InvalidDocument;
use App\Documento\Domain\MovementDirection;
use App\Documento\Domain\UnsupportedFile;
use App\Documento\Domain\VatRate;
use App\Documento\Infrastructure\Storage\LocalDocumentFileStore;
use App\Shared\Domain\Clock;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\Transaction;
use PHPUnit\Framework\TestCase;

final class DocumentRegistrationTest extends TestCase
{
    private const CLIENT = '00000000-0000-4000-8000-000000000014';

    private const OTHER = '00000000-0000-4000-8000-000000000015';

    private const FIRM = '00000000-0000-4000-8000-000000000001';

    private const DOCUMENT = '00000000-0000-4000-8000-000000000031';

    public function testAFileThatIsNotPdfOrImageIsRejected(): void
    {
        $this->expectException(UnsupportedFile::class);

        $this->store()->execute(self::CLIENT, 'nota.txt', 'text/plain', 'hola');
    }

    public function testAnEmptyFileIsRejected(): void
    {
        $this->expectException(UnsupportedFile::class);

        $this->store()->execute(self::CLIENT, 'vacio.pdf', 'application/pdf', '');
    }

    public function testADraftKeepsPartialDataAndStaysEditable(): void
    {
        $documents = $this->stored();
        $saved = $this->draft($documents)->execute(
            self::CLIENT,
            self::DOCUMENT,
            'gasto',
            '',
            'Papelería Norte',
            '',
            '',
            '',
        );

        self::assertSame(DocumentStatus::Draft, $saved->status());
        self::assertTrue($saved->isEditable());
        self::assertSame(MovementDirection::Expense, $saved->direction());
        self::assertSame('Papelería Norte', $saved->partyName());
        self::assertNull($saved->quotaCents());
        self::assertCount(1, (new ListClientDocuments($documents))->pending(self::CLIENT));
    }

    public function testConfirmingWithoutTheDataNamesTheMissingFields(): void
    {
        try {
            $this->confirm($this->stored())->execute(self::CLIENT, self::DOCUMENT, '', '', '', '', '', '');
            self::fail('The incomplete document was accepted.');
        } catch (IncompleteDocument $exception) {
            self::assertSame(
                ['direction', 'issuedOn', 'partyName', 'taxId', 'base', 'vatRate'],
                $exception->fields(),
            );
        }
    }

    public function testTheTotalIsTheBasePlusTheQuota(): void
    {
        $saved = $this->draft($this->stored())->execute(
            self::CLIENT,
            self::DOCUMENT,
            '',
            '',
            '',
            '',
            '100,00',
            '21',
        );

        self::assertSame(2100, $saved->quotaCents());
        self::assertSame(12100, $saved->totalCents());
    }

    public function testAZeroVatRateKeepsTheTotalEqualToTheBase(): void
    {
        $saved = $this->draft($this->stored())->execute(
            self::CLIENT,
            self::DOCUMENT,
            '',
            '',
            '',
            '',
            '50,00',
            '0',
        );

        self::assertSame(0, $saved->quotaCents());
        self::assertSame(5000, $saved->totalCents());
    }

    public function testConfirmingCalculatesTheQuotaAndDoesNotValidate(): void
    {
        $saved = $this->confirm($this->stored())->execute(
            self::CLIENT,
            self::DOCUMENT,
            'ingreso',
            '2026-03-12',
            'Cliente Ejemplo',
            '00000000t',
            '100,00',
            '21',
        );

        self::assertSame(DocumentStatus::Submitted, $saved->status());
        self::assertFalse($saved->isEditable());
        self::assertSame(10000, $saved->baseCents());
        self::assertSame(2100, $saved->quotaCents());
        self::assertSame(12100, $saved->totalCents());
        self::assertSame('00000000T', $saved->taxId());
        self::assertSame([], (new ListClientDocuments(new RegistrationDocumentRepository([$saved])))->pending(self::CLIENT));
    }

    public function testAConfirmedDocumentCannotBeEdited(): void
    {
        $documents = $this->stored();
        $this->confirm($documents)->execute(
            self::CLIENT,
            self::DOCUMENT,
            'gasto',
            '2026-03-12',
            'Cliente Ejemplo',
            '00000000T',
            '100,00',
            '21',
        );

        $this->expectException(InvalidDocument::class);

        $this->draft($documents)->execute(self::CLIENT, self::DOCUMENT, 'gasto', '', 'Otro', '', '', '');
    }

    public function testAnotherClientCannotOpenTheDocument(): void
    {
        $this->expectException(DocumentNotFound::class);

        (new OpenClientDocument(new RegistrationDocumentRepository([$this->received()])))
            ->execute(self::OTHER, self::DOCUMENT);
    }

    public function testAClientWithoutAFirmKeepsTheDocumentUnlinked(): void
    {
        $document = $this->store()->execute(self::CLIENT, 'ticket.png', 'image/png', 'png');

        self::assertNull($document->firmId());
        self::assertSame(self::CLIENT, $document->clientUserId());
        self::assertSame(DocumentStatus::Uploaded, $document->status());
    }

    public function testALinkedClientCopiesOnlyThatFirm(): void
    {
        $clients = new RegistrationClientRepository();
        $clients->save(new Client(
            '00000000-0000-4000-8000-000000000021',
            self::CLIENT,
            ClientKind::SelfEmployed,
            'Mesa',
            self::FIRM,
        ));

        $document = $this->store($clients)->execute(self::CLIENT, 'ticket.png', 'image/png', 'png');

        self::assertSame(self::FIRM, $document->firmId());
    }

    public function testSpanishAmountsAndTheVatRounding(): void
    {
        $grouped = DocumentForm::read('', '', '', '', '1.250,50', '');
        $dotted = DocumentForm::read('', '', '', '', '1250.50', '');

        self::assertSame(125050, $grouped->baseCents());
        self::assertSame(125050, $dotted->baseCents());
        self::assertSame(2100, VatRate::General->quotaFor(10000));
        self::assertSame(210, VatRate::General->quotaFor(1001));
    }

    public function testTheFileStoreDoesNotReadOutsideItsFolder(): void
    {
        $root = sys_get_temp_dir().'/aselto-docs-'.bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root.'/secret.txt', 'secret');
        $store = new LocalDocumentFileStore($root);

        try {
            $path = $store->write(self::DOCUMENT, 'png', 'png');

            self::assertSame('png', $store->read($path));

            $this->expectException(DocumentNotFound::class);
            $store->read('../secret.txt');
        } finally {
            $this->removeTree($root);
        }
    }

    private function store(?RegistrationClientRepository $clients = null): StoreClientDocument
    {
        return new StoreClientDocument(
            $clients ?? $this->clients(),
            new RegistrationDocumentRepository(),
            new RegistrationFileStore(),
            new RegistrationIds(self::DOCUMENT),
            new RegistrationClock(),
            new RegistrationTransaction(),
        );
    }

    private function stored(): RegistrationDocumentRepository
    {
        $documents = new RegistrationDocumentRepository();
        $documents->save($this->received());

        return $documents;
    }

    private function received(): Document
    {
        return Document::receive(
            self::DOCUMENT,
            self::CLIENT,
            null,
            'ticket.png',
            'documents/'.self::DOCUMENT.'.png',
            'image/png',
            new \DateTimeImmutable('2026-11-07 02:00:00 UTC'),
        );
    }

    private function draft(RegistrationDocumentRepository $documents): SaveDocumentDraft
    {
        return new SaveDocumentDraft(new OpenClientDocument($documents), $documents, new RegistrationTransaction());
    }

    private function confirm(RegistrationDocumentRepository $documents): ConfirmDocument
    {
        return new ConfirmDocument(new OpenClientDocument($documents), $documents, new RegistrationTransaction());
    }

    private function clients(): RegistrationClientRepository
    {
        $clients = new RegistrationClientRepository();
        $clients->save(new Client(
            '00000000-0000-4000-8000-000000000021',
            self::CLIENT,
            ClientKind::SelfEmployed,
            'Ana',
            null,
        ));

        return $clients;
    }

    private function removeTree(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $this->removeTree($path.'/'.$entry);
        }

        rmdir($path);
    }
}

final class RegistrationClientRepository implements ClientRepository
{
    /** @var array<string, Client> */
    private array $clients = [];

    public function findByUserId(string $userId): ?Client
    {
        return $this->clients[$userId] ?? null;
    }

    public function findByFirm(string $firmId): array
    {
        return [];
    }

    public function findWithoutFirm(): array
    {
        return [];
    }

    public function save(Client $client): void
    {
        $this->clients[$client->userId()] = $client;
    }
}

final class RegistrationDocumentRepository implements DocumentRepository
{
    /** @param list<Document> $documents */
    public function __construct(private array $documents = [])
    {
    }

    public function find(string $id): ?Document
    {
        foreach ($this->documents as $document) {
            if ($document->id() === $id) {
                return $document;
            }
        }

        return null;
    }

    public function findByOwner(string $clientUserId): array
    {
        return array_values(array_filter(
            $this->documents,
            static fn (Document $document): bool => $document->clientUserId() === $clientUserId,
        ));
    }

    public function save(Document $document): void
    {
        foreach ($this->documents as $index => $stored) {
            if ($stored->id() === $document->id()) {
                $this->documents[$index] = $document;

                return;
            }
        }

        $this->documents[] = $document;
    }
}

final class RegistrationFileStore implements DocumentFileStore
{
    public function write(string $documentId, string $extension, string $contents): string
    {
        return 'documents/'.$documentId.'.'.$extension;
    }

    public function read(string $relativePath): string
    {
        return '';
    }
}

final class RegistrationIds implements IdGenerator
{
    public function __construct(private readonly string $id)
    {
    }

    public function next(): string
    {
        return $this->id;
    }
}

final class RegistrationClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-11-07 02:00:00 UTC');
    }
}

final class RegistrationTransaction implements Transaction
{
    public function run(callable $operation): void
    {
        $operation();
    }
}
