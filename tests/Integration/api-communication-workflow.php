<?php

// Explicit check against the migrated local database; all synthetic fixtures roll back.
require dirname(__DIR__).'/bootstrap.php';

use App\Asesoria\Domain\Firm;
use App\Asesoria\Infrastructure\Persistence\DoctrineFirmRepository;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Infrastructure\Persistence\DoctrineClientRepository;
use App\Comunicacion\Infrastructure\Storage\LocalAttachmentStore;
use App\Documento\Application\DocumentForm;
use App\Documento\Domain\Document;
use App\Documento\Infrastructure\Persistence\DoctrineDocumentRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Kernel;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ApiCommunicationCheckKernel extends Kernel
{
    protected function build(Symfony\Component\DependencyInjection\ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class implements Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface {
            public function process(Symfony\Component\DependencyInjection\ContainerBuilder $container): void
            {
                $container->getDefinition('security.token_storage')->setPublic(true);
            }
        });
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/api-communication-check';
    }
}

$kernel = new ApiCommunicationCheckKernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$before = hash('sha256', json_encode($db->fetchAllAssociative('SELECT * FROM client_document ORDER BY id'), JSON_THROW_ON_ERROR));
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void { if (!$condition) { throw new RuntimeException($message); } ++$checks; };
$uuid = static fn (): string => Uuid::v4()->toRfc4122();
$temporary = [];
$accounts = [];
$files = new LocalAttachmentStore(dirname(__DIR__, 2));
$db->beginTransaction();
try {
    $users = new DoctrineUserRepository($em);
    $clients = new DoctrineClientRepository($em);
    $documents = new DoctrineDocumentRepository($em);
    $firms = new DoctrineFirmRepository($em);
    $firmId = $uuid();
    $otherFirmId = $uuid();
    $firms->save(new Firm($firmId, 'Asesoría sintética comunicación API'));
    $firms->save(new Firm($otherFirmId, 'Otra asesoría sintética comunicación API'));
    $password = 'synthetic-message-password';
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
    $accounts = $profiles = [];
    foreach (['personal', 'linked', 'stranger', 'admin', 'advisor', 'other', 'root'] as $name) {
        $role = match ($name) {
            'admin' => Role::FirmAdmin, 'advisor', 'other' => Role::Advisor, 'root' => Role::Superadmin, default => Role::Client,
        };
        $firm = match ($name) {
            'linked', 'admin', 'advisor' => $firmId, 'other' => $otherFirmId, default => null,
        };
        $user = new User($uuid(), $uuid().'@example.test', $hash, $role, $firm, true);
        $users->save($user);
        $accounts[$name] = $user;
        if (Role::Client === $role) {
            $profiles[$name] = new Client($uuid(), $user->id(), ClientKind::SelfEmployed, 'Cliente sintético comunicación API', $firm);
            $clients->save($profiles[$name]);
        }
    }
    $http = static function (string $url, string $method = 'GET', ?array $data = null, ?string $token = null, string $contentType = 'application/json', array $uploads = []) use ($kernel, $em): Response {
        $em->clear();
        $kernel->getContainer()->get('security.token_storage')->setToken(null);
        $server = ['CONTENT_TYPE' => $contentType];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $multipart = 'multipart/form-data' === $contentType;
        $request = Request::create('http://localhost'.$url, $method, $multipart ? ($data ?? []) : [], files: $uploads, server: $server, content: $multipart ? null : (null === $data ? null : json_encode((object) $data, JSON_THROW_ON_ERROR)));
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    };
    $json = static fn (Response $response): array => json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    $tokens = [];
    foreach ($accounts as $name => $user) {
        $response = $http('/api/v1/auth/login', 'POST', ['email' => $user->email(), 'password' => $password]);
        $check(200 === $response->getStatusCode(), 'Synthetic login failed.');
        $tokens[$name] = $json($response)['token'];
    }
    $url = '/api/v1/conversations';
    $get = static fn (string $path, string $actor): Response => $http($path, token: $tokens[$actor]);
    $post = static fn (string $path, array $data, string $actor): Response => $http($path, 'POST', $data, $tokens[$actor]);
    $draft = ['owner_id' => $accounts['linked']->id(), 'subject' => 'Consulta sintética', 'text' => 'Mensaje de ejemplo'];
    $check(401 === $http($url)->getStatusCode(), 'JWT is required.');
    $check(403 === $get($url, 'root')->getStatusCode(), 'Superadmin cannot access fiscal conversations.');
    $check([] === $json($get('/api/v1/conversation-recipients', 'personal'))['recipients'], 'Unlinked client has no recipients.');
    foreach (['personal', 'stranger', 'other'] as $actor) {
        $check(404 === $post($url, $draft, $actor)->getStatusCode(), 'Cannot start for foreign client.');
    }
    $recipients = $json($get('/api/v1/conversation-recipients', 'admin'))['recipients'];
    $check(1 === count($recipients) && $accounts['linked']->id() === $recipients[0]['owner_id'], 'Admin recipients scoped to current firm.');
    $check(1 === count($json($get('/api/v1/conversation-recipients', 'linked'))['recipients']), 'Client recipient is self.');
    $check(404 === $get('/api/v1/conversation-recipients/'.$accounts['linked']->id().'/documents', 'other')->getStatusCode(), 'Shared documents scoped.');
    foreach ([[], [...$draft, 'request' => 'true'], [...$draft, 'subject' => []], [...$draft, 'owner_id' => 'invalid'], [...$draft, 'document_id' => ''], [...$draft, 'firm_id' => $otherFirmId]] as $bad) {
        $check(400 === $post($url, $bad, 'admin')->getStatusCode(), 'Strict create shape.');
    }
    $check(415 === $http($url, 'POST', $draft, $tokens['admin'], 'text/plain')->getStatusCode(), 'JSON or multipart required.');
    $check(400 === $post($url.'?owner_id='.$accounts['stranger']->id(), $draft, 'admin')->getStatusCode(), 'Query cannot override recipient.');
    $check(422 === $post($url, [...$draft, 'text' => ' '], 'admin')->getStatusCode(), 'Empty text rejected.');
    $check(422 === $post($url, [...$draft, 'subject' => str_repeat('a', 121)], 'admin')->getStatusCode(), 'Subject bounded.');
    $check(422 === $post($url, [...$draft, 'text' => str_repeat('a', 4001)], 'admin')->getStatusCode(), 'Text bounded.');
    $response = $post($url, $draft, 'linked');
    $conversation = $json($response);
    $id = $conversation['id'];
    $thread = $url.'/'.$id;
    $check(201 === $response->getStatusCode() && $thread === $response->headers->get('Location'), 'Create returns protected location.');
    $check(!isset($conversation['version']) && 0 === $conversation['unread'], 'No stale persistence version exposed.');
    $check(1 === $json($get($url, 'admin'))['conversations'][0]['unread'], 'List does not mark read.');
    $check(1 === $json($get($url, 'admin'))['conversations'][0]['unread'], 'Repeated list still unread.');
    $check(0 === $json($get($thread, 'admin'))['unread'], 'Opening marks displayed messages read.');
    $check(0 === $json($get($url, 'admin'))['conversations'][0]['unread'], 'Reader persisted.');
    $check(1 === $json($get($url, 'advisor'))['conversations'][0]['unread'], 'Reading is individual for team.');
    $reply = $json($post($thread.'/messages', ['text' => 'Respuesta del equipo'], 'advisor'));
    $check(2 === count($reply['messages']) && 1 === $reply['unread'], 'Reply preserves unseen messages.');
    $check(1 === $json($get($url, 'linked'))['conversations'][0]['unread'], 'Client sees team reply as unread.');
    foreach (['personal', 'stranger', 'other'] as $actor) {
        $check(404 === $get($thread, $actor)->getStatusCode(), 'Other client or firm cannot read.');
        $check(404 === $post($thread.'/messages', ['text' => 'No permitido'], $actor)->getStatusCode(), 'Other client or firm cannot reply.');
    }
    $check(422 === $post($thread.'/resolve', [], 'admin')->getStatusCode(), 'Ordinary conversation cannot resolve.');
    $check(404 === $post($url, [...$draft, 'request' => true], 'linked')->getStatusCode(), 'Client cannot create requests.');
    $request = $json($post($url, [...$draft, 'request' => true], 'admin'));
    $requestUrl = $url.'/'.$request['id'];
    $check($request['request'] && null === $request['resolved_at'], 'Staff creates pending request.');
    $response = $post($requestUrl.'/messages', ['text' => 'He aportado la información'], 'linked');
    $check(200 === $response->getStatusCode() && null === $json($response)['resolved_at'], 'Client reply does not resolve.');
    $check(404 === $post($requestUrl.'/resolve', [], 'linked')->getStatusCode(), 'Client cannot confirm resolution.');
    $check(400 === $post($requestUrl.'/resolve', ['resolved_by' => $accounts['admin']->id()], 'advisor')->getStatusCode(), 'Cannot inject resolution author.');
    $check(204 === $post($requestUrl.'/resolve', [], 'advisor')->getStatusCode(), 'Advisor resolves request.');
    $resolved = $json($get($requestUrl, 'linked'));
    $check($accounts['advisor']->id() === $resolved['resolved_by'] && null !== $resolved['resolved_at'], 'Resolution audit persists.');
    $check(422 === $post($requestUrl.'/resolve', [], 'admin')->getStatusCode(), 'Double resolution rejected.');
    $document = Document::receive($uuid(), $accounts['linked']->id(), $firmId, 'synthetic.pdf', 'synthetic-api-message/no-file.pdf', 'application/pdf', new DateTimeImmutable('2026-11-01'));
    $documents->save($document);
    $sharedUrl = '/api/v1/conversation-recipients/'.$accounts['linked']->id().'/documents';
    $check([] === $json($get($sharedUrl, 'admin'))['documents'], 'Uploaded document not shared.');
    $check(422 === $post($url, [...$draft, 'document_id' => $document->id()], 'linked')->getStatusCode(), 'Unshared document cannot be linked.');
    $details = DocumentForm::read('gasto', '2026-11-01', 'Proveedor sintético', '00000000T', '100', '21');
    $documents->save($document->confirm($details));
    $check(1 === count($json($get($sharedUrl, 'linked'))['documents']), 'Submitted document is linkable.');
    $linkedDocument = $json($post($url, [...$draft, 'document_id' => $document->id()], 'advisor'));
    $check($document->id() === $linkedDocument['document_id'], 'Shared document link preserved.');
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
    $temporary[] = $source = tempnam(sys_get_temp_dir(), 'api-message-');
    file_put_contents($source, $pdf);
    $upload = static fn (): UploadedFile => new UploadedFile($source, 'áé 100%.pdf', 'text/plain', UPLOAD_ERR_OK, true);
    $multipart = static fn (string $path, array $data, array $uploads, string $actor = 'admin'): Response => $http($path, 'POST', $data, $tokens[$actor], 'multipart/form-data', $uploads);
    $response = $multipart($url, [...$draft, 'request' => 'true'], ['attachments' => [$upload(), $upload(), $upload()]]);
    $withFiles = $json($response);
    $check(201 === $response->getStatusCode() && $withFiles['request'], 'Multipart boolean and three attachments.');
    $file = $withFiles['messages'][0]['attachments'][0];
    $check('application/pdf' === $file['media_type'] && !isset($file['path']) && str_starts_with($file['url'], $url.'/'), 'MIME detected from content; no storage path.');
    foreach (['admin', 'advisor', 'linked'] as $actor) {
        $response = $get($file['url'], $actor);
        $check(200 === $response->getStatusCode() && $pdf === $response->getContent(), 'Authorized byte-exact download.');
        $check(str_contains((string) $response->headers->get('Content-Disposition'), 'attachment') && 'nosniff' === $response->headers->get('X-Content-Type-Options') && str_contains((string) $response->headers->get('Cache-Control'), 'no-store'), 'Private safe download headers.');
    }
    foreach (['personal', 'stranger', 'other'] as $actor) {
        $check(404 === $get($file['url'], $actor)->getStatusCode(), 'Unauthorized file hidden.');
    }
    $check(401 === $http($file['url'])->getStatusCode(), 'Attachment requires JWT.');
    $check(404 === $get($thread.'/attachments/'.$file['id'], 'linked')->getStatusCode(), 'Attachment cannot be read through another thread.');
    $response = $multipart($thread.'/messages', ['text' => 'Con adjunto'], ['attachments' => [$upload()]], 'linked');
    $check(200 === $response->getStatusCode() && 1 === count($json($response)['messages'][2]['attachments']), 'Reply accepts general attachment.');
    $check(422 === $multipart($thread.'/messages', ['text' => 'Demasiados'], ['attachments' => [$upload(), $upload(), $upload(), $upload()]])->getStatusCode(), 'Four files rejected.');
    $check(400 === $multipart($thread.'/messages', ['text' => 'Forma incorrecta'], ['attachments' => $upload()])->getStatusCode(), 'Scalar upload rejected.');
    $check(400 === $multipart($thread.'/messages', ['text' => 'Campo desconocido'], ['file' => $upload()])->getStatusCode(), 'Unknown file field rejected.');
    file_put_contents($source, 'No es un PDF');
    $check(422 === $multipart($thread.'/messages', ['text' => 'Archivo falso'], ['attachments' => [$upload()]])->getStatusCode(), 'MIME mismatch rejected.');
    file_put_contents($source, str_repeat('a', 8_000_001));
    $check(413 === $multipart($thread.'/messages', ['text' => 'Archivo grande'], ['attachments' => [$upload()]])->getStatusCode(), 'Eight MB limit enforced.');
    $check([] === $json($get($url, 'other'))['conversations'], 'Other firm list empty.');
    $db->executeStatement('UPDATE identity_user SET firm_id = NULL WHERE id = ?', [$accounts['linked']->id()]);
    $db->executeStatement('UPDATE client_profile SET firm_id = NULL WHERE user_id = ?', [$accounts['linked']->id()]);
    $check(404 === $get($thread, 'linked')->getStatusCode(), 'Unlinked former client loses access.');
    $check(404 === $get($thread, 'admin')->getStatusCode(), 'Former firm loses access after unlinking.');
    $check(404 === $get($file['url'], 'advisor')->getStatusCode(), 'Attachment access checks current linkage.');
} finally {
    $paths = [];
    foreach ($db->fetchAllAssociative('SELECT messages FROM client_conversation WHERE owner_id = ?', [isset($accounts['linked']) ? $accounts['linked']->id() : '']) as $row) {
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
    $kernel->shutdown();
}
$after = hash('sha256', json_encode($db->fetchAllAssociative('SELECT * FROM client_document ORDER BY id'), JSON_THROW_ON_ERROR));
$check($before === $after, 'Existing documents unchanged.');
echo "API communication integration checks: $checks passed.\nSynthetic data and attachments removed; no tokens printed.\n";
