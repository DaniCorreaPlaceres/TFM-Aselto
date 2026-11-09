<?php

// Run explicitly in Docker on the migrated local database; synthetic fixtures roll back.
require dirname(__DIR__).'/bootstrap.php';

use App\Asesoria\Domain\Firm;
use App\Asesoria\Infrastructure\Persistence\DoctrineFirmRepository;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Infrastructure\Persistence\DoctrineClientRepository;
use App\Comunicacion\Application\ConversationAccess;
use App\Comunicacion\Application\Messaging;
use App\Comunicacion\Domain\ConversationNotFound;
use App\Comunicacion\Domain\InvalidMessage;
use App\Comunicacion\Infrastructure\Persistence\DoctrineConversationRepository;
use App\Comunicacion\Infrastructure\Storage\LocalAttachmentStore;
use App\Documento\Application\DocumentForm;
use App\Documento\Domain\Document;
use App\Documento\Infrastructure\Persistence\DoctrineDocumentRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Kernel;
use App\Shared\Infrastructure\DoctrineClientTransaction;
use App\Shared\Infrastructure\SymfonyIdGenerator;
use App\Shared\Infrastructure\SystemClock;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

$kernel = new Kernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$before = hash('sha256', json_encode([$db->fetchAllAssociative('SELECT * FROM client_document ORDER BY id'), $db->fetchAllAssociative('SELECT * FROM financial_quarter ORDER BY owner_id, scope, year, number')], JSON_THROW_ON_ERROR));
$checkCount = 0;
$check = static function (bool $condition, string $message) use (&$checkCount): void { if (!$condition) { throw new RuntimeException($message); } ++$checkCount; };
$expect = static function (string $exception, callable $operation) use ($check): void {
    try {
        $operation();
    } catch (Throwable $error) {
        $check($error instanceof $exception, 'Unexpected exception '.$error::class.': '.$error->getMessage());

        return;
    }
    throw new RuntimeException('Expected '.$exception);
};
$uuid = static fn (): string => Uuid::v4()->toRfc4122();
$files = new LocalAttachmentStore(dirname(__DIR__, 2));
$ownerId = $uuid();
$temporary = [];
$db->beginTransaction();
try {
    $firms = new DoctrineFirmRepository($em);
    $users = new DoctrineUserRepository($em);
    $clients = new DoctrineClientRepository($em);
    $documents = new DoctrineDocumentRepository($em);
    $conversations = new DoctrineConversationRepository($em);
    $firm = new Firm($uuid(), 'Asesoría sintética mensajes');
    $otherFirm = new Firm($uuid(), 'Otra asesoría sintética mensajes');
    $firms->save($firm);
    $firms->save($otherFirm);
    $owner = new User($ownerId, $uuid().'@example.test', 'synthetic-hash', Role::Client, $firm->id(), true);
    $solo = new User($uuid(), $uuid().'@example.test', 'synthetic-hash', Role::Client, null, true);
    $otherOwner = new User($uuid(), $uuid().'@example.test', 'synthetic-hash', Role::Client, $firm->id(), true);
    $admin = new User($uuid(), $uuid().'@example.test', 'synthetic-hash', Role::FirmAdmin, $firm->id(), true);
    $advisor = new User($uuid(), $uuid().'@example.test', 'synthetic-hash', Role::Advisor, $firm->id(), true);
    $other = new User($uuid(), $uuid().'@example.test', 'synthetic-hash', Role::Advisor, $otherFirm->id(), true);
    $root = new User($uuid(), $uuid().'@example.test', 'synthetic-hash', Role::Superadmin, null, true);
    foreach ([$owner, $solo, $otherOwner, $admin, $advisor, $other, $root] as $user) {
        $users->save($user);
    }
    foreach ([$owner, $solo, $otherOwner] as $user) {
        $clients->save(new Client($uuid(), $user->id(), ClientKind::SelfEmployed, 'Cliente sintético mensajes', $user->firmId()));
    }
    $clock = new SystemClock();
    $transaction = new DoctrineClientTransaction($em);
    $messaging = new Messaging(new ConversationAccess($users, $clients), $conversations, $clients, $documents, $files, $transaction, new SymfonyIdGenerator(), $clock);
    $document = Document::receive($uuid(), $ownerId, $firm->id(), 'synthetic.pdf', 'synthetic.pdf', 'application/pdf', $clock->now())->confirm(DocumentForm::read('gasto', '2026-11-09', 'Ejemplo', '00000000T', '100', '21'));
    $documents->save($document);
    $upload = ['name' => '../áé 100%.pdf', 'contents' => "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n"];
    $thread = $messaging->start($admin->id(), $ownerId, 'Solicitud sintética', 'Necesitamos información', true, $document->id(), [$upload]);
    $file = $thread->messages[0]->attachments[0];
    $check(1 === $messaging->list($ownerId)[0]->unread($ownerId), 'The owner must see an unread message.');
    $messaging->open($ownerId, $thread->id);
    $check(0 === $messaging->list($ownerId)[0]->unread($ownerId), 'Opening must mark the displayed sequence as read.');
    $messaging->reply($ownerId, $thread->id, 'Información aportada');
    $check(null === $conversations->find($thread->id)->resolvedAt, 'Reply must not resolve a request.');
    $messaging->open($admin->id(), $thread->id);
    $check(0 === $messaging->list($admin->id())[0]->unread($admin->id()), 'Admin reading progress must persist.');
    $check(2 === $messaging->list($advisor->id())[0]->unread($advisor->id()), 'Advisor reading must be independent from admin.');
    $expect(ConversationNotFound::class, fn () => $messaging->resolve($ownerId, $thread->id));
    $messaging->resolve($advisor->id(), $thread->id);
    $em->clear();
    $stored = $conversations->find($thread->id);
    $check($advisor->id() === $stored->resolvedBy && null !== $stored->resolvedAt, 'Resolution author and time must persist.');
    $check(2 === count($stored->messages) && $document->id() === $stored->documentId, 'Messages and linked document must survive reload.');
    $check($upload['contents'] === $messaging->attachment($ownerId, $thread->id, $file->id)['contents'], 'Private attachment must survive reload.');
    foreach ([$other, $otherOwner, $root, $solo] as $forbidden) {
        $expect(ConversationNotFound::class, fn () => $messaging->open($forbidden->id(), $thread->id));
        $expect(ConversationNotFound::class, fn () => $messaging->attachment($forbidden->id(), $thread->id, $file->id));
    }
    $expect(InvalidMessage::class, fn () => $messaging->start($ownerId, $ownerId, 'Falso PDF', 'Texto', uploads: [['name' => 'fake.pdf', 'contents' => '<html>fake</html>']]));
    $expect(ConversationNotFound::class, fn () => $messaging->start($ownerId, $ownerId, 'Solicitud no permitida', 'Texto', true));

    $sessions = [];
    $http = static function (User $actor, string $url, string $method = 'GET', array $data = [], array $uploads = []) use ($kernel, &$sessions): Symfony\Component\HttpFoundation\Response {
        $security = SecurityUser::fromDomain($actor);
        $session = $sessions[$actor->id()] ??= new Session(new MockArraySessionStorage());
        $session->set('_security_main', serialize(new UsernamePasswordToken($security, 'main', $security->getRoles())));
        $request = Request::create('http://localhost'.$url, $method, $data, files: $uploads);
        $request->setSession($session);
        $request->cookies->set($session->getName(), $session->getId());
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    };
    $csrf = static function (Symfony\Component\HttpFoundation\Response $response): string {
        preg_match_all('/name="_csrf_token" value="([^"]*)"/', $response->getContent(), $matches);

        return html_entity_decode(end($matches[1]) ?: '', ENT_QUOTES);
    };
    $response = $http($owner, '/mensajes');
    $check(200 === $response->getStatusCode() && str_contains($response->getContent(), 'Solicitud sintética'), 'Client conversation list must render.');
    $response = $http($advisor, '/mensajes/'.$thread->id);
    $check(200 === $response->getStatusCode() && str_contains($response->getContent(), 'Solicitud resuelta'), 'Staff thread must render messages and request resolution.');
    $response = $http($owner, '/mensajes/'.$thread->id.'/adjuntos/'.$file->id);
    $check(200 === $response->getStatusCode() && $upload['contents'] === $response->getContent(), 'Authorized download must return the attachment.');
    $check(str_starts_with((string) $response->headers->get('Content-Disposition'), 'attachment;') && 'nosniff' === $response->headers->get('X-Content-Type-Options'), 'Downloads must use safe attachment headers.');
    foreach ([$other, $otherOwner] as $forbidden) {
        $check(404 === $http($forbidden, '/mensajes/'.$thread->id)->getStatusCode(), 'Other actor thread must be hidden.');
        $check(404 === $http($forbidden, '/mensajes/'.$thread->id.'/adjuntos/'.$file->id)->getStatusCode(), 'Other actor download must be hidden.');
    }
    $check(403 === $http($root, '/mensajes')->getStatusCode(), 'Superadmin must not access messaging.');
    $response = $http($solo, '/mensajes');
    $check(200 === $response->getStatusCode() && str_contains($response->getContent(), 'no tienes una asesoría'), 'Unlinked clients need a clear empty state.');
    $check(200 === $http($owner, '/inicio')->getStatusCode(), 'Client home must render communication counts.');
    $response = $http($admin, '/mensajes/nuevo?owner='.$ownerId);
    $check(200 === $response->getStatusCode() && str_contains($response->getContent(), $document->id()), 'New message form must offer shared documents.');
    $data = ['owner' => $ownerId, 'subject' => 'Formulario web', 'text' => '<script>sin ejecutar</script>', 'request' => '1', 'document' => $document->id(), '_csrf_token' => $csrf($response)];
    $invalid = $http($admin, '/mensajes/nuevo', 'POST', [...$data, '_csrf_token' => 'invalid']);
    $check(200 === $invalid->getStatusCode() && str_contains($invalid->getContent(), 'caducado') && str_contains($invalid->getContent(), 'Formulario web'), 'Invalid CSRF must preserve the draft text.');
    $temporary[] = $temporaryFile = tempnam(sys_get_temp_dir(), 'message-test-');
    file_put_contents($temporaryFile, $upload['contents']);
    $response = $http($admin, '/mensajes/nuevo', 'POST', $data, ['attachments' => [new UploadedFile($temporaryFile, 'synthetic.pdf', 'application/pdf', UPLOAD_ERR_OK, true)]]);
    $check(302 === $response->getStatusCode(), 'Valid message and upload must redirect to the new thread.');
    $url = $response->headers->get('Location');
    $newId = basename($url);
    $check(1 === count($conversations->find($newId)->messages[0]->attachments), 'Web attachment must persist.');
    $response = $http($owner, $url);
    $check(str_contains($response->getContent(), '&lt;script&gt;sin ejecutar&lt;/script&gt;'), 'Message HTML must be escaped.');
    $response = $http($owner, $url, 'POST', ['intent' => 'reply', 'text' => 'Respuesta web', '_csrf_token' => $csrf($response)]);
    $check(302 === $response->getStatusCode() && 2 === count($conversations->find($newId)->messages), 'Web reply must append without losing messages.');
    $response = $http($advisor, $url);
    $response = $http($advisor, $url, 'POST', ['intent' => 'resolve', '_csrf_token' => $csrf($response)]);
    $check(302 === $response->getStatusCode() && $advisor->id() === $conversations->find($newId)->resolvedBy, 'Advisor web resolution must persist.');
    echo 'Messaging integration checks: '.$checkCount." passed.\n";
} finally {
    $paths = [];
    foreach ($db->fetchAllAssociative('SELECT messages FROM client_conversation WHERE owner_id = ?', [$ownerId]) as $row) {
        foreach (json_decode($row['messages'], true, flags: JSON_THROW_ON_ERROR) as $message) {
            foreach ($message['files'] as $file) {
                $paths[] = $file['path'];
            }
        }
    }
    $db->rollBack();
    $em->clear();
    foreach ($paths as $path) {
        $files->remove($path);
    }
    foreach ($temporary as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    $after = hash('sha256', json_encode([$db->fetchAllAssociative('SELECT * FROM client_document ORDER BY id'), $db->fetchAllAssociative('SELECT * FROM financial_quarter ORDER BY owner_id, scope, year, number')], JSON_THROW_ON_ERROR));
    $check($before === $after, 'Existing documents or frozen quarters were modified.');
    $kernel->shutdown();
}
echo "Synthetic fixtures and attachments removed; existing fiscal data unchanged.\n";
