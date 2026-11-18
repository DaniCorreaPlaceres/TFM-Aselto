<?php

namespace App\Tests\Comunicacion;

use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\ClientRepository;
use App\Comunicacion\Application\ConversationAccess;
use App\Comunicacion\Application\MessageUpload;
use App\Comunicacion\Application\Messaging;
use App\Comunicacion\Domain\AttachmentStore;
use App\Comunicacion\Domain\Conversation;
use App\Comunicacion\Domain\ConversationNotFound;
use App\Comunicacion\Domain\ConversationRepository;
use App\Comunicacion\Domain\InvalidMessage;
use App\Comunicacion\Domain\Message;
use App\Comunicacion\Infrastructure\Persistence\DoctrineConversation;
use App\Documento\Application\DocumentForm;
use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\ClientTransaction;
use App\Shared\Domain\Clock;
use App\Shared\Domain\IdGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessagingTest extends TestCase
{
    private const OWNER = '00000000-0000-4000-8000-000000000001';
    private const ADMIN = '00000000-0000-4000-8000-000000000002';
    private const ADVISOR = '00000000-0000-4000-8000-000000000003';
    private const OTHER = '00000000-0000-4000-8000-000000000004';
    private const ROOT = '00000000-0000-4000-8000-000000000005';
    private const INACTIVE = '00000000-0000-4000-8000-000000000006';
    private const SOLO = '00000000-0000-4000-8000-000000000007';
    private const OTHER_OWNER = '00000000-0000-4000-8000-000000000008';
    private const FIRM = '00000000-0000-4000-8000-000000000010';
    private const OTHER_FIRM = '00000000-0000-4000-8000-000000000011';
    private const DOC = '00000000-0000-4000-8000-000000000030';
    /** @var array<string, Conversation> */
    private array $records = [];
    /** @var array<string, string> */
    private array $files = [];
    private ?Document $document = null;
    private bool $linked = true;
    private Messaging $messaging;

    protected function setUp(): void
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('find')->willReturnCallback(static fn (string $id): ?User => match ($id) {
            self::OWNER, self::OTHER_OWNER => new User($id, 'client'.$id.'@example.test', 'hash', Role::Client, self::FIRM, true),
            self::ADMIN => new User($id, 'admin@example.test', 'hash', Role::FirmAdmin, self::FIRM, true),
            self::ADVISOR => new User($id, 'advisor@example.test', 'hash', Role::Advisor, self::FIRM, true),
            self::OTHER => new User($id, 'other@example.test', 'hash', Role::Advisor, self::OTHER_FIRM, true),
            self::ROOT => new User($id, 'root@example.test', 'hash', Role::Superadmin, null, true),
            self::INACTIVE => new User($id, 'inactive@example.test', 'hash', Role::Advisor, self::FIRM, false),
            self::SOLO => new User($id, 'solo@example.test', 'hash', Role::Client, null, true),
            default => null,
        });
        $clients = $this->createStub(ClientRepository::class);
        $client = fn (): Client => new Client('00000000-0000-4000-8000-000000000020', self::OWNER, ClientKind::SelfEmployed, 'Cliente sintético', $this->linked ? self::FIRM : self::OTHER_FIRM);
        $clients->method('findByUserId')->willReturnCallback(static fn (string $id): ?Client => self::OWNER === $id ? $client() : (self::SOLO === $id ? new Client('00000000-0000-4000-8000-000000000021', self::SOLO, ClientKind::SelfEmployed, 'Sin asesoría', null) : null));
        $clients->method('findByFirm')->willReturnCallback(static fn (string $id): array => self::FIRM === $id ? [$client()] : []);
        $repository = $this->createStub(ConversationRepository::class);
        $repository->method('find')->willReturnCallback(fn (string $id): ?Conversation => $this->records[$id] ?? null);
        $repository->method('forOwner')->willReturnCallback(fn (string $id): array => array_values(array_filter($this->records, static fn (Conversation $c): bool => $c->ownerId === $id)));
        $repository->method('forFirm')->willReturnCallback(fn (string $id): array => array_values(array_filter($this->records, static fn (Conversation $c): bool => $c->firmId === $id)));
        $repository->method('save')->willReturnCallback(function (Conversation $c): void { $this->records[$c->id] = $c; });
        $documents = $this->createStub(DocumentRepository::class);
        $documents->method('find')->willReturnCallback(fn (): ?Document => $this->document);
        $documents->method('findByOwner')->willReturnCallback(fn (): array => null === $this->document ? [] : [$this->document]);
        $files = $this->createStub(AttachmentStore::class);
        $files->method('write')->willReturnCallback(function (string $id, string $extension, string $contents): string {
            $path = $id.'.'.$extension;
            $this->files[$path] = $contents;

            return $path;
        });
        $files->method('read')->willReturnCallback(function (string $path): string { return $this->files[$path]; });
        $files->method('remove')->willReturnCallback(function (string $path): void { unset($this->files[$path]); });
        $transaction = $this->createStub(ClientTransaction::class);
        $transaction->method('run')->willReturnCallback(static fn (string $id, callable $operation): mixed => $operation());
        $ids = $this->createStub(IdGenerator::class);
        $sequence = 100;
        $ids->method('next')->willReturnCallback(static function () use (&$sequence): string { return sprintf('00000000-0000-4000-8000-%012d', ++$sequence); });
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-11-09 03:00 UTC'));
        $this->messaging = new Messaging(new ConversationAccess($users, $clients), $repository, $clients, $documents, $files, $transaction, $ids, $clock);
    }

    public function testBothSidesCanStartAndTeamSharesAttentionWithIndividualReading(): void
    {
        $thread = $this->messaging->start(self::OWNER, self::OWNER, 'Consulta', 'Mensaje del cliente');
        self::assertSame(1, $this->messaging->list(self::ADMIN)[0]->unread(self::ADMIN));
        $this->messaging->open(self::ADMIN, $thread->id);
        self::assertSame(0, $this->messaging->list(self::ADMIN)[0]->unread(self::ADMIN));
        self::assertSame(1, $this->messaging->list(self::ADVISOR)[0]->unread(self::ADVISOR));
        $this->messaging->reply(self::ADVISOR, $thread->id, 'Respuesta del gestor');
        self::assertSame(1, $this->messaging->list(self::OWNER)[0]->unread(self::OWNER));
        self::assertCount(2, $this->messaging->open(self::OWNER, $thread->id)->messages);
        self::assertSame(0, $this->messaging->list(self::OWNER)[0]->unread(self::OWNER));
        $this->messaging->start(self::ADMIN, self::OWNER, 'Otra consulta', 'Mensaje del admin');
        self::assertCount(2, $this->messaging->list(self::OWNER));
    }

    public function testAResponseDoesNotResolveARequestAndStaffResolutionIsAudited(): void
    {
        $thread = $this->messaging->start(self::ADVISOR, self::OWNER, 'Solicitud', 'Necesitamos información', true);
        $reply = $this->messaging->reply(self::OWNER, $thread->id, 'La información solicitada');
        self::assertNull($reply->resolvedAt);
        $this->messaging->resolve(self::ADMIN, $thread->id);
        $resolved = $this->messaging->open(self::OWNER, $thread->id);
        self::assertSame(self::ADMIN, $resolved->resolvedBy);
        self::assertNotNull($resolved->resolvedAt);
        self::assertCount(2, $resolved->messages);
        $later = $this->messaging->reply(self::OWNER, $thread->id, 'Gracias');
        self::assertNotNull($later->resolvedAt);
        $this->expectException(InvalidMessage::class);
        $this->messaging->resolve(self::ADVISOR, $thread->id);
    }

    public function testClientCannotCreateRequests(): void
    {
        $this->expectException(ConversationNotFound::class);
        $this->messaging->start(self::OWNER, self::OWNER, 'Solicitud', 'Texto', true);
    }

    public function testClientCannotResolveRequests(): void
    {
        $thread = $this->messaging->start(self::ADMIN, self::OWNER, 'Solicitud', 'Texto', true);
        $this->expectException(ConversationNotFound::class);
        $this->messaging->resolve(self::OWNER, $thread->id);
    }

    /** @return iterable<string, array{string}> */
    public static function forbidden(): iterable
    {
        yield 'other firm' => [self::OTHER];
        yield 'other client' => [self::OTHER_OWNER];
        yield 'superadmin' => [self::ROOT];
        yield 'inactive' => [self::INACTIVE];
        yield 'unlinked client' => [self::SOLO];
    }

    #[DataProvider('forbidden')]
    public function testUnauthorizedActorsCannotReadReplyResolveOrDownload(string $actor): void
    {
        $thread = $this->messaging->start(self::ADMIN, self::OWNER, 'Privado', 'Texto', true, uploads: [self::pdf()]);
        foreach ([fn () => $this->messaging->open($actor, $thread->id), fn () => $this->messaging->reply($actor, $thread->id, 'Intrusión'), fn () => $this->messaging->resolve($actor, $thread->id), fn () => $this->messaging->attachment($actor, $thread->id, $thread->messages[0]->attachments[0]->id)] as $operation) {
            try {
                $operation();
                self::fail('Access must be denied.');
            } catch (ConversationNotFound) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testCurrentClientRelationshipIsRequiredEvenForExistingConversation(): void
    {
        $thread = $this->messaging->start(self::ADMIN, self::OWNER, 'Consulta', 'Texto');
        $this->linked = false;
        self::assertSame([], $this->messaging->list(self::ADMIN));
        $this->expectException(ConversationNotFound::class);
        $this->messaging->open(self::ADMIN, $thread->id);
    }

    public function testReadingOldSequenceDoesNotConsumeLaterMessages(): void
    {
        $at = new \DateTimeImmutable();
        $thread = new Conversation('id', self::OWNER, self::FIRM, 'Asunto', false, null, [new Message(self::ADMIN, $at, 'Primero')]);
        $later = $thread->reply(new Message(self::ADMIN, $at, 'Segundo'))->read(self::OWNER, 1);
        self::assertSame(1, $later->unread(self::OWNER));
        self::assertSame(1, $later->read(self::OWNER, 0)->unread(self::OWNER));
    }

    public function testAttachmentsArePrivateAndRoundTripWithMessagesAndReaders(): void
    {
        $thread = $this->messaging->start(self::OWNER, self::OWNER, 'Adjunto', '<script>texto sin ejecutar</script>', uploads: [self::pdf()]);
        $file = $thread->messages[0]->attachments[0];
        self::assertSame('ejemplo.pdf', $file->name);
        self::assertSame(self::pdf()['contents'], $this->messaging->attachment(self::ADMIN, $thread->id, $file->id)['contents']);
        $entity = new DoctrineConversation();
        $entity->apply($thread->read(self::OWNER, 1));
        self::assertEquals($thread->read(self::OWNER, 1), $entity->toDomain());
        $this->expectException(ConversationNotFound::class);
        $this->messaging->attachment(self::OWNER, $thread->id, self::DOC);
    }

    public function testInvalidConversationRemovesAlreadyWrittenAttachments(): void
    {
        try {
            $this->messaging->start(self::OWNER, self::OWNER, '', 'Texto', uploads: [self::pdf()]);
            self::fail();
        } catch (InvalidMessage) {
            self::assertSame([], $this->files);
            self::assertSame([], $this->records);
        }
    }

    public function testSharedDocumentLinkRequiresOwnerFirmAndSharedStatus(): void
    {
        $this->document = Document::receive(self::DOC, self::OWNER, self::FIRM, 'ejemplo.pdf', 'synthetic.pdf', 'application/pdf', new \DateTimeImmutable())->confirm(DocumentForm::read('gasto', '2026-11-09', 'Ejemplo', '00000000T', '10', '21'));
        $thread = $this->messaging->start(self::OWNER, self::OWNER, 'Documento', 'Consulta', documentId: self::DOC);
        self::assertSame(self::DOC, $thread->documentId);
        self::assertCount(1, $this->messaging->sharedDocuments(self::ADMIN, self::OWNER));
        $this->document = Document::receive(self::DOC, self::OWNER, null, 'ejemplo.pdf', 'synthetic.pdf', 'application/pdf', new \DateTimeImmutable());
        self::assertSame([], $this->messaging->sharedDocuments(self::OWNER, self::OWNER));
        $this->expectException(InvalidMessage::class);
        $this->messaging->start(self::OWNER, self::OWNER, 'Privado', 'Consulta', documentId: self::DOC);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidFiles(): iterable
    {
        yield 'empty' => [''];
        yield 'too large' => [str_repeat('a', 8_000_001)];
        yield 'HTML disguised as PDF' => ['<html>example</html>'];
        yield 'SVG' => ['<svg xmlns="http://www.w3.org/2000/svg"></svg>'];
    }

    #[DataProvider('invalidFiles')]
    public function testInvalidFileContentsAreRejected(string $contents): void
    {
        $this->expectException(InvalidMessage::class);
        MessageUpload::read('fake.pdf', $contents);
    }

    public function testRealPngAndSafeUnicodeNamesAreAccepted(): void
    {
        $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aQ1sAAAAASUVORK5CYII=', true);
        $file = MessageUpload::read('../carpeta/áé 100%.png', $contents);
        self::assertSame('áé 100%.png', $file['name']);
        self::assertSame('image/png', $file['type']);
    }

    public function testMoreThanThreeAttachmentsAreRejected(): void
    {
        $this->expectException(InvalidMessage::class);
        $this->messaging->start(self::OWNER, self::OWNER, 'Archivos', 'Texto', uploads: array_fill(0, 4, self::pdf()));
    }

    public function testThreeAttachmentsAndMaximumTextLengthsAreAccepted(): void
    {
        $thread = $this->messaging->start(self::OWNER, self::OWNER, str_repeat('á', 120), str_repeat('ñ', 4000), uploads: array_fill(0, 3, self::pdf()));
        self::assertCount(3, $thread->messages[0]->attachments);
        self::assertSame(4000, mb_strlen($thread->messages[0]->text));
        self::assertSame(120, mb_strlen($thread->subject));
    }

    public function testWhitespaceOnlyMessagesAreRejected(): void
    {
        $this->expectException(InvalidMessage::class);
        $this->messaging->start(self::OWNER, self::OWNER, 'Asunto', " \n\t ");
    }

    public function testExcessiveSubjectIsRejected(): void
    {
        $this->expectException(InvalidMessage::class);
        $this->messaging->start(self::OWNER, self::OWNER, str_repeat('á', 121), 'Texto');
    }

    public function testExcessiveMessageIsRejected(): void
    {
        $this->expectException(InvalidMessage::class);
        $this->messaging->start(self::OWNER, self::OWNER, 'Asunto', str_repeat('ñ', 4001));
    }

    public function testStandaloneClientHasNoRecipientOrConversations(): void
    {
        self::assertSame([], $this->messaging->recipients(self::SOLO));
        self::assertSame([], $this->messaging->list(self::SOLO));
        $this->expectException(ConversationNotFound::class);
        $this->messaging->start(self::SOLO, self::SOLO, 'Sin destinatario', 'Texto');
    }

    /** @return array{name: string, contents: string} */
    private static function pdf(): array
    {
        return ['name' => '../../ejemplo.pdf', 'contents' => "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n"];
    }
}
