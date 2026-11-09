<?php

namespace App\Tests\Documento;

use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\ClientRepository;
use App\Documento\Application\ConfirmDocument;
use App\Documento\Application\DocumentForm;
use App\Documento\Application\DocumentWithholdingPolicy;
use App\Documento\Application\FirmDocumentAccess;
use App\Documento\Application\ListClientDocuments;
use App\Documento\Application\ListFirmDocuments;
use App\Documento\Application\OpenClientDocument;
use App\Documento\Application\ReadSupportingDocument;
use App\Documento\Application\ReviewSubmittedDocument;
use App\Documento\Application\SaveDocumentDraft;
use App\Documento\Application\SupplySupportingDocument;
use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentChanged;
use App\Documento\Domain\DocumentDetails;
use App\Documento\Domain\DocumentFileStore;
use App\Documento\Domain\DocumentNotFound;
use App\Documento\Domain\DocumentRepository;
use App\Documento\Domain\DocumentStatus;
use App\Documento\Domain\InvalidDocument;
use App\Documento\Domain\InvalidWithholding;
use App\Documento\Domain\ReviewAction;
use App\Documento\Domain\SupportingDocument;
use App\Documento\Domain\UnsupportedFile;
use App\Documento\Infrastructure\Persistence\DoctrineDocument;
use App\Documento\Ui\DocumentFileResponse;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\ClientTransaction;
use App\Shared\Domain\Clock;
use App\Shared\Domain\IdGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdvisoryInboxTest extends TestCase
{
    private const FIRM = '00000000-0000-4000-8000-000000000001';
    private const OTHER_FIRM = '00000000-0000-4000-8000-000000000002';
    private const ADMIN = '00000000-0000-4000-8000-000000000012';
    private const ADVISOR = '00000000-0000-4000-8000-000000000013';
    private const OWNER = '00000000-0000-4000-8000-000000000014';
    private const OTHER_OWNER = '00000000-0000-4000-8000-000000000015';
    private const OTHER_ADVISOR = '00000000-0000-4000-8000-000000000016';
    private const ID = '00000000-0000-4000-8000-000000000031';
    private const FILE_ID = '00000000-0000-4000-8000-000000000041';

    /** @var array<string, Document> */
    private array $records = [];
    private DocumentRepository $documents;
    private ClientRepository $clients;
    private FirmDocumentAccess $access;
    private DocumentWithholdingPolicy $policy;
    private Clock $clock;
    private ClientTransaction $transaction;
    private ReviewSubmittedDocument $review;

    protected function setUp(): void
    {
        $users = $this->createStub(UserRepository::class);
        $accounts = [
            self::ADMIN => new User(self::ADMIN, 'admin@example.test', 'hash', Role::FirmAdmin, self::FIRM, true),
            self::ADVISOR => new User(self::ADVISOR, 'advisor@example.test', 'hash', Role::Advisor, self::FIRM, true),
            self::OWNER => new User(self::OWNER, 'owner@example.test', 'hash', Role::Client, self::FIRM, true),
            self::OTHER_ADVISOR => new User(self::OTHER_ADVISOR, 'other@example.test', 'hash', Role::Advisor, self::OTHER_FIRM, true),
            '00000000-0000-4000-8000-000000000011' => new User('00000000-0000-4000-8000-000000000011', 'root@example.test', 'hash', Role::Superadmin, null, true),
            '00000000-0000-4000-8000-000000000017' => new User('00000000-0000-4000-8000-000000000017', 'inactive@example.test', 'hash', Role::Advisor, self::FIRM, false),
        ];
        $users->method('find')->willReturnCallback(static fn (string $id): ?User => $accounts[$id] ?? null);
        $this->clients = $this->createStub(ClientRepository::class);
        $this->clients->method('findByUserId')->willReturnCallback(static fn (string $id): ?Client => match ($id) {
            self::OWNER => new Client('00000000-0000-4000-8000-000000000021', $id, ClientKind::SelfEmployed, 'Cliente de ejemplo', self::FIRM),
            self::OTHER_OWNER => new Client('00000000-0000-4000-8000-000000000022', $id, ClientKind::SelfEmployed, 'Otro cliente', self::OTHER_FIRM),
            default => null,
        });
        $this->documents = $this->createStub(DocumentRepository::class);
        $this->documents->method('find')->willReturnCallback(fn (string $id): ?Document => $this->records[$id] ?? null);
        $this->documents->method('save')->willReturnCallback(function (Document $document): void { $this->records[$document->id()] = $document; });
        $this->documents->method('findByOwner')->willReturnCallback(fn (string $id): array => array_values(array_filter($this->records, static fn (Document $document): bool => $document->clientUserId() === $id)));
        $this->documents->method('findByFirm')->willReturnCallback(fn (string $id): array => array_values(array_filter($this->records, static fn (Document $document): bool => $document->firmId() === $id)));
        $this->clock = $this->createStub(Clock::class);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-11-08 23:30:00 UTC'));
        $this->transaction = $this->createStub(ClientTransaction::class);
        $this->transaction->method('run')->willReturnCallback(static function (string $ownerId, callable $operation): mixed { return $operation(); });
        $this->access = new FirmDocumentAccess($users, $this->clients, $this->documents);
        $this->policy = new DocumentWithholdingPolicy($this->clients);
        $this->review = new ReviewSubmittedDocument($this->access, $this->documents, $this->policy, $this->clock, $this->transaction, closedPeriods: $this->createStub(\App\Shared\Domain\ClosedPeriodGuard::class));
        $this->records[self::ID] = $this->received()->confirm($this->details(), $this->clock->now());
    }

    public function testAdminAndAdvisorCanCorrectAndValidateWithAuditSnapshots(): void
    {
        foreach ([self::ADMIN, self::ADVISOR] as $actor) {
            $this->records[self::ID] = $this->received()->confirm($this->details(), $this->clock->now());
            $corrected = DocumentForm::read('ingreso', '2026-11-08', 'Cliente corregido', '00000000T', '200', '21', withholdingRate: '15');
            $validated = $this->review->execute($actor, self::ID, 1, ReviewAction::Validated, $corrected);
            self::assertSame(DocumentStatus::Validated, $validated->status());
            self::assertSame(21200, $validated->totalCents());
            self::assertSame('documents/original.png', $validated->storedPath());
            $event = $validated->reviewHistory()[1];
            self::assertSame($actor, $event->actorId);
            self::assertSame(10000, $event->before->baseCents());
            self::assertSame(20000, $event->after?->baseCents());
            self::assertEquals($this->clock->now(), $event->occurredAt);
        }
    }

    #[DataProvider('deniedActors')]
    public function testUnauthorizedActorsCannotReadOrReview(string $actor): void
    {
        $this->expectException(DocumentNotFound::class);
        $this->review->execute($actor, self::ID, 1, ReviewAction::Validated, $this->details());
    }

    /** @return iterable<string, array{string}> */
    public static function deniedActors(): iterable
    {
        yield 'other firm' => [self::OTHER_ADVISOR];
        yield 'client' => [self::OWNER];
        yield 'superadmin' => ['00000000-0000-4000-8000-000000000011'];
        yield 'inactive' => ['00000000-0000-4000-8000-000000000017'];
        yield 'unknown' => ['00000000-0000-4000-8000-000000000019'];
    }

    public function testInboxExcludesDraftsOtherFirmsAndFormerClients(): void
    {
        $draft = $this->received('00000000-0000-4000-8000-000000000032');
        $foreign = $this->received('00000000-0000-4000-8000-000000000033', self::OTHER_OWNER, self::OTHER_FIRM)->confirm($this->details());
        $former = $this->received('00000000-0000-4000-8000-000000000034', self::OTHER_OWNER)->confirm($this->details());
        $personal = $this->received('00000000-0000-4000-8000-000000000035', self::OWNER, null)->confirm($this->details());
        foreach ([$draft, $foreign, $former, $personal] as $document) {
            $this->records[$document->id()] = $document;
        }
        $list = (new ListFirmDocuments($this->access, $this->documents))->execute(self::ADVISOR);
        self::assertCount(1, $list);
        self::assertSame(self::ID, $list[0]->document->id());
        self::assertSame('Cliente de ejemplo', $list[0]->clientName);
    }

    public function testRejectedRecordCanBeCorrectedAndResubmittedWithoutLosingHistory(): void
    {
        $rejected = $this->review->execute(self::ADVISOR, self::ID, 1, ReviewAction::Rejected, reason: 'Corrige la fecha.');
        self::assertTrue($rejected->isEditable());
        self::assertCount(1, (new ListClientDocuments($this->documents))->pending(self::OWNER));
        $open = new OpenClientDocument($this->documents);
        $draft = (new SaveDocumentDraft($open, $this->documents, $this->transaction, $this->policy, closedPeriods: $this->createStub(\App\Shared\Domain\ClosedPeriodGuard::class)))->execute(self::OWNER, self::ID, 'gasto', '2026-10-01', 'Proveedor', '00000000T', '100', '21');
        self::assertSame(DocumentStatus::Draft, $draft->status());
        self::assertSame('Corrige la fecha.', $draft->reviewHistory()[1]->reason);
        self::assertCount(0, (new ListFirmDocuments($this->access, $this->documents))->execute(self::ADVISOR));
        $resent = (new ConfirmDocument($open, $this->documents, $this->transaction, $this->policy, $this->clock, closedPeriods: $this->createStub(\App\Shared\Domain\ClosedPeriodGuard::class)))->execute(self::OWNER, self::ID, 'gasto', '2026-10-01', 'Proveedor', '00000000T', '100', '21');
        self::assertSame(DocumentStatus::Submitted, $resent->status());
        self::assertCount(3, $resent->reviewHistory());
        self::assertSame(ReviewAction::Submitted, $resent->reviewHistory()[2]->action);
        self::assertCount(1, (new ListFirmDocuments($this->access, $this->documents))->execute(self::ADVISOR));
    }

    #[DataProvider('invalidReasons')]
    public function testRejectionAndRequestRequireAReason(ReviewAction $action, string $reason): void
    {
        $this->expectException(InvalidDocument::class);
        $this->review->execute(self::ADVISOR, self::ID, 1, $action, reason: $reason);
    }

    /** @return iterable<string, array{ReviewAction, string}> */
    public static function invalidReasons(): iterable
    {
        foreach ([ReviewAction::Rejected, ReviewAction::Requested] as $action) {
            foreach (['', '   ', str_repeat('x', 1001)] as $index => $reason) {
                yield $action->value.$index => [$action, $reason];
            }
        }
    }

    public function testRequestBlocksValidationAndAppearsInClientPending(): void
    {
        $pending = $this->review->execute(self::ADVISOR, self::ID, 1, ReviewAction::Requested, reason: 'Adjunta el justificante de pago.');
        self::assertSame(DocumentStatus::Submitted, $pending->status());
        self::assertFalse($pending->isEditable());
        self::assertCount(1, (new ListClientDocuments($this->documents))->pending(self::OWNER));
        $this->expectException(InvalidDocument::class);
        $this->review->execute(self::ADVISOR, self::ID, 1, ReviewAction::Validated, $this->details());
    }

    public function testSupportingFilePreservesOriginalAndFinancialDataAndAllowsLaterValidation(): void
    {
        $this->review->execute(self::ADVISOR, self::ID, 1, ReviewAction::Requested, reason: 'Adjunta el justificante.');
        $files = $this->createMock(DocumentFileStore::class);
        $files->expects($this->once())->method('write')->with(self::FILE_ID, 'pdf', '%PDF')->willReturn('documents/'.self::FILE_ID.'.pdf');
        $updated = $this->supply($files)->execute(self::OWNER, self::ID, 1, '../pago.pdf', 'application/pdf', '%PDF');
        self::assertNull($updated->pendingRequest());
        self::assertSame(DocumentStatus::Submitted, $updated->status());
        self::assertSame(12100, $updated->totalCents());
        self::assertSame('documents/original.png', $updated->storedPath());
        self::assertSame('pago.pdf', $updated->supportingFiles()[0]->originalName);
        $read = new ReadSupportingDocument(new OpenClientDocument($this->documents), $this->access);
        self::assertSame(self::FILE_ID, $read->forOwner(self::OWNER, self::ID, self::FILE_ID)->id);
        self::assertSame(self::FILE_ID, $read->forFirm(self::ADVISOR, self::ID, self::FILE_ID)->id);
        $validated = $this->review->execute(self::ADMIN, self::ID, 1, ReviewAction::Validated, $this->details());
        $record = new DoctrineDocument();
        $record->apply($validated);
        self::assertEquals($validated, $record->toDomain());
        self::assertCount(4, $validated->reviewHistory());
    }

    public function testOtherFirmCannotDownloadSupportingFile(): void
    {
        $this->addSupportingFile();
        $this->expectException(DocumentNotFound::class);
        (new ReadSupportingDocument(new OpenClientDocument($this->documents), $this->access))->forFirm(self::OTHER_ADVISOR, self::ID, self::FILE_ID);
    }

    public function testOtherOwnerCannotDownloadSupportingFile(): void
    {
        $this->addSupportingFile();
        $this->expectException(DocumentNotFound::class);
        (new ReadSupportingDocument(new OpenClientDocument($this->documents), $this->access))->forOwner(self::OTHER_OWNER, self::ID, self::FILE_ID);
    }

    public function testUnknownSupportingFileIsNotReturned(): void
    {
        $this->addSupportingFile();
        $this->expectException(DocumentNotFound::class);
        (new ReadSupportingDocument(new OpenClientDocument($this->documents), $this->access))->forOwner(self::OWNER, self::ID, '00000000-0000-4000-8000-000000000099');
    }

    public function testUploadWithoutARequestDoesNotWriteAFile(): void
    {
        $files = $this->createMock(DocumentFileStore::class);
        $files->expects($this->never())->method('write');
        $this->expectException(InvalidDocument::class);
        $this->supply($files)->execute(self::OWNER, self::ID, 1, 'pago.pdf', 'application/pdf', '%PDF');
    }

    public function testUnauthorizedUploadDoesNotWriteAFile(): void
    {
        $this->addSupportingFile();
        $files = $this->createMock(DocumentFileStore::class);
        $files->expects($this->never())->method('write');
        $this->expectException(DocumentNotFound::class);
        $this->supply($files)->execute(self::OTHER_OWNER, self::ID, 1, 'pago.pdf', 'application/pdf', '%PDF');
    }

    public function testStaleReviewIsRejected(): void
    {
        $this->expectException(DocumentChanged::class);
        $this->review->execute(self::ADVISOR, self::ID, 0, ReviewAction::Validated, $this->details());
    }

    public function testStaleUploadDoesNotWriteAFile(): void
    {
        $this->review->execute(self::ADVISOR, self::ID, 1, ReviewAction::Requested, reason: 'Adjunta el pago.');
        $files = $this->createMock(DocumentFileStore::class);
        $files->expects($this->never())->method('write');
        $this->expectException(DocumentChanged::class);
        $this->supply($files)->execute(self::OWNER, self::ID, 0, 'pago.pdf', 'application/pdf', '%PDF');
    }

    public function testInvalidSupportingFileIsRejectedBeforeWriting(): void
    {
        $this->review->execute(self::ADVISOR, self::ID, 1, ReviewAction::Requested, reason: 'Adjunta el pago.');
        $files = $this->createMock(DocumentFileStore::class);
        $files->expects($this->never())->method('write');
        $this->expectException(UnsupportedFile::class);
        $this->supply($files)->execute(self::OWNER, self::ID, 1, 'pago.txt', 'text/plain', 'texto');
    }

    public function testValidatedRecordCannotBeReviewedAgain(): void
    {
        $this->review->execute(self::ADVISOR, self::ID, 1, ReviewAction::Validated, $this->details());
        $this->expectException(InvalidDocument::class);
        $this->review->execute(self::ADMIN, self::ID, 1, ReviewAction::Rejected, reason: 'Cambio posterior.');
    }

    public function testValidationCannotAddWithholdingToCompanyIncome(): void
    {
        $clients = $this->createStub(ClientRepository::class);
        $clients->method('findByUserId')->willReturn(new Client('00000000-0000-4000-8000-000000000021', self::OWNER, ClientKind::Company, 'Sociedad de ejemplo', self::FIRM));
        $review = new ReviewSubmittedDocument($this->access, $this->documents, new DocumentWithholdingPolicy($clients), $this->clock, $this->transaction, closedPeriods: $this->createStub(\App\Shared\Domain\ClosedPeriodGuard::class));
        $this->expectException(InvalidWithholding::class);
        $review->execute(self::ADVISOR, self::ID, 1, ReviewAction::Validated, DocumentForm::read('ingreso', '2026-11-08', 'Cliente', '00000000T', '100', '21', withholdingRate: '15'));
    }

    public function testDownloadSupportsSpanishNamesAndPercentCharacters(): void
    {
        $response = DocumentFileResponse::create('%PDF', 'application/pdf', 'Justificante de José 20%.pdf');
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('filename*=', (string) $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('Jos%C3%A9%2020%25.pdf', (string) $response->headers->get('Content-Disposition'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    private function addSupportingFile(): void
    {
        $document = $this->records[self::ID]->requestSupportingDocument('Adjunta el pago.', self::ADVISOR, $this->clock->now());
        $this->records[self::ID] = $document->attachSupportingDocument(new SupportingDocument(self::FILE_ID, 'pago.pdf', 'documents/pago.pdf', 'application/pdf', $this->clock->now()), self::OWNER, $this->clock->now());
    }

    private function supply(DocumentFileStore $files): SupplySupportingDocument
    {
        $ids = $this->createStub(IdGenerator::class);
        $ids->method('next')->willReturn(self::FILE_ID);

        return new SupplySupportingDocument(new OpenClientDocument($this->documents), $this->documents, $files, $ids, $this->clock, $this->transaction);
    }

    private function details(): DocumentDetails
    {
        return DocumentForm::read('gasto', '2026-11-08', 'Proveedor de ejemplo', '00000000T', '100', '21');
    }

    private function received(string $id = self::ID, string $owner = self::OWNER, ?string $firm = self::FIRM): Document
    {
        return Document::receive($id, $owner, $firm, 'original.png', 'documents/original.png', 'image/png', $this->clock->now());
    }
}
