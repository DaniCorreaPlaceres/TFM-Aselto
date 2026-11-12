<?php

require dirname(__DIR__).'/bootstrap.php';

use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Infrastructure\Persistence\DoctrineClientRepository;
use App\Documento\Application\DocumentForm;
use App\Documento\Domain\DocumentExtractor;
use App\Documento\Domain\DocumentStatus;
use App\Documento\Domain\Proposal;
use App\Documento\Domain\ProposalOrigin;
use App\Documento\Domain\Tax;
use App\Documento\Infrastructure\Persistence\DoctrineDocumentRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Kernel;
use App\Shared\Domain\FiscalContact;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class UploadZoneExtractor implements DocumentExtractor
{
    public function propose(string $mediaType, string $contents, Tax $tax = Tax::Vat, bool $allowsWithholding = false): Proposal
    {
        return new Proposal(DocumentForm::read('gasto', '2026-11-01', 'Proveedor ficticio', '00000000T', '100', '21', $tax), ProposalOrigin::Sample);
    }
}

final class UploadZoneCheckKernel extends Kernel
{
    protected function build(Symfony\Component\DependencyInjection\ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class implements Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface {
            public function process(Symfony\Component\DependencyInjection\ContainerBuilder $container): void
            {
                $container->register(UploadZoneExtractor::class, UploadZoneExtractor::class);
                $container->setAlias(DocumentExtractor::class, UploadZoneExtractor::class);
            }
        });
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/upload-zone-check';
    }
}

$kernel = new UploadZoneCheckKernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$documents = new DoctrineDocumentRepository($em);
$before = hash('sha256', json_encode($db->fetchAllAssociative('SELECT * FROM client_document ORDER BY id'), JSON_THROW_ON_ERROR));
$ownerId = Uuid::v4()->toRfc4122();
$source = tempnam(sys_get_temp_dir(), 'upload-zone-');
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void { if (!$condition) { throw new RuntimeException($message); } ++$checks; };
$db->beginTransaction();
try {
    $user = new User($ownerId, Uuid::v4()->toRfc4122().'@example.test', 'synthetic', Role::Client, null, true);
    new DoctrineUserRepository($em)->save($user);
    new DoctrineClientRepository($em)->save(new Client(Uuid::v4()->toRfc4122(), $ownerId, ClientKind::SelfEmployed, 'Cliente ficticio de subida', null, FiscalContact::required('00000000T', '600000000', 'Dirección ficticia 1', '28001', 'Municipio de ejemplo', 'Madrid')));
    $session = new Session(new MockArraySessionStorage());
    $http = static function (string $url, string $method = 'GET', array $data = [], array $uploads = []) use ($kernel, $session, $user): Symfony\Component\HttpFoundation\Response {
        $security = SecurityUser::fromDomain($user);
        $session->set('_security_main', serialize(new UsernamePasswordToken($security, 'main', $security->getRoles())));
        $request = Request::create('http://localhost'.$url, $method, $data, files: $uploads);
        $request->setSession($session);
        $request->cookies->set($session->getName(), $session->getId());
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    };
    $response = $http('/documentos/nuevo');
    $html = $response->getContent();
    $check(200 === $response->getStatusCode(), 'Upload page available.');
    $check(1 === substr_count($html, 'enctype="multipart/form-data"') && 1 === preg_match_all('/<input[^>]*name="file"/', $html), 'One form and one canonical file field.');
    preg_match_all('/name="_csrf_token" value="([^"]*)"/', $html, $matches);
    $csrf = html_entity_decode(end($matches[1]), ENT_QUOTES);
    $check(422 === $http('/documentos/nuevo', 'POST', ['_csrf_token' => 'invalid'])->getStatusCode(), 'CSRF failure renders with Turbo-compatible status.');
    $check(422 === $http('/documentos/nuevo', 'POST', ['_csrf_token' => $csrf])->getStatusCode(), 'Missing file rejected server-side.');
    file_put_contents($source, 'Not a PDF');
    $upload = static fn (): array => ['file' => new UploadedFile($source, 'example.pdf', 'application/pdf', UPLOAD_ERR_OK, true)];
    $check(422 === $http('/documentos/nuevo', 'POST', ['_csrf_token' => $csrf], $upload())->getStatusCode(), 'Declared PDF with invalid content rejected.');
    $check([] === $documents->findByOwner($ownerId), 'Invalid requests create no document.');
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
    file_put_contents($source, $pdf);
    clearstatcache(true, $source);
    $response = $http('/documentos/nuevo', 'POST', ['_csrf_token' => $csrf], $upload());
    $check(302 === $response->getStatusCode(), 'Valid file redirects to human review.');
    $stored = $documents->findByOwner($ownerId);
    $check(1 === count($stored) && DocumentStatus::Draft === $stored[0]->status(), 'Extraction proposes without confirming.');
    $check(200 === $http((string) $response->headers->get('Location'))->getStatusCode(), 'Review page renders.');
    $download = $http('/documentos/'.$stored[0]->id().'/archivo');
    $check(200 === $download->getStatusCode() && $pdf === $download->getContent(), 'Original remains downloadable and byte-exact.');
} finally {
    $paths = array_map(static fn ($document): string => $document->storedPath(), $documents->findByOwner($ownerId));
    $db->rollBack();
    $em->clear();
    $storage = realpath(dirname(__DIR__, 2).'/var/storage');
    foreach ($paths as $relative) {
        $absolute = false === $storage ? false : realpath($storage.'/'.$relative);
        if (false !== $absolute) {
            if (!str_starts_with($absolute, $storage.DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Unsafe cleanup path.');
            }
            unlink($absolute);
        }
    }
    unlink($source);
    $kernel->shutdown();
}
$check($before === hash('sha256', json_encode($db->fetchAllAssociative('SELECT * FROM client_document ORDER BY id'), JSON_THROW_ON_ERROR)), 'Existing documents unchanged.');
echo "Document upload integration checks: $checks passed.\nSynthetic fixtures and files removed; no external extraction.\n";
