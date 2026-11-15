<?php

require dirname(__DIR__).'/bootstrap.php';

use App\Asesoria\Domain\Firm;
use App\Asesoria\Infrastructure\Persistence\DoctrineFirmRepository;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Infrastructure\Persistence\DoctrineClientRepository;
use App\Comunicacion\Domain\Conversation;
use App\Comunicacion\Domain\Message;
use App\Comunicacion\Infrastructure\Persistence\DoctrineConversationRepository;
use App\Documento\Application\DocumentForm;
use App\Documento\Domain\Document;
use App\Documento\Infrastructure\Persistence\DoctrineDocumentRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Kernel;
use App\Tests\Support\AuthenticatedSession;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class ResourceLimitsCheckKernel extends Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->register('cache.resource_limits', ArrayAdapter::class)->setPublic(true);
                $container->getDefinition('security.token_storage')->setPublic(true);
            }
        });
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/resource-limits-check';
    }
}
$kernel = new ResourceLimitsCheckKernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();
$db = $em->getConnection();
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void { if (!$condition) { throw new RuntimeException($message); } ++$checks; };
$http = static function (User $actor, string $path, string $method = 'GET', bool $api = false) use ($kernel, $container, $em) {
    $em->clear();
    $container->get('security.token_storage')->setToken(null);
    $session = new Session(new MockArraySessionStorage());
    $request = Request::create('http://localhost'.$path, $method);
    if ($api) {
        $request->headers->set('Authorization', 'Bearer '.$container->get('lexik_jwt_authentication.jwt_manager')->create(SecurityUser::fromDomain($actor)));
    } else {
        $session->set('_security_main', serialize(new UsernamePasswordToken(SecurityUser::fromDomain($actor), 'main', SecurityUser::fromDomain($actor)->getRoles())));
        AuthenticatedSession::start($session);
    }
    $request->setSession($session);
    $request->cookies->set($session->getName(), $session->getId());
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
};
$json = static function ($response): array {
    if (200 !== $response->getStatusCode()) {
        throw new RuntimeException('Expected 200; received '.$response->getStatusCode().': '.substr($response->getContent(), 0, 400));
    }

    return json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
};
$before = (int) $db->fetchOne('SELECT COUNT(*) FROM client_document');
$db->beginTransaction();
try {
    $firms = [Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122()];
    $owners = [];
    $admins = [];
    foreach ($firms as $i => $firm) {
        (new DoctrineFirmRepository($em))->save(new Firm($firm, 'Asesoría sintética límites '.$i));
        foreach ([Role::Client, Role::FirmAdmin] as $role) {
            $actor = new User(Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122().'@example.test', 'synthetic-unused-hash', $role, $firm, true);
            (new DoctrineUserRepository($em))->save($actor);
            if (Role::Client === $role) {
                $owners[$i] = $actor;
                (new DoctrineClientRepository($em))->save(new Client(Uuid::v4()->toRfc4122(), $actor->id(), ClientKind::SelfEmployed, 'Cliente sintético '.$i, $firm));
            } else {
                $admins[$i] = $actor;
            }
        }
    }
    $documents = new DoctrineDocumentRepository($em);
    for ($i = 0; $i < 131; ++$i) {
        $document = Document::receive(Uuid::v4()->toRfc4122(), $owners[0]->id(), $firms[0], sprintf('Documento %03d.pdf', $i), 'synthetic/not-written.pdf', 'application/pdf', new DateTimeImmutable());
        $details = DocumentForm::read('ingreso', '2026-11-01', sprintf('Tercero %03d', $i), '00000000T', '100', '21');
        $documents->save($document->confirm($details));
    }
    $documents->save(Document::receive(Uuid::v4()->toRfc4122(), $owners[1]->id(), $firms[1], 'Documento ajeno.pdf', 'synthetic/not-written.pdf', 'application/pdf', new DateTimeImmutable()));
    $conversationId = Uuid::v4()->toRfc4122();
    (new DoctrineConversationRepository($em))->save(new Conversation($conversationId, $owners[0]->id(), $firms[0], 'Solicitud sintética', true, null, [new Message($admins[0]->id(), new DateTimeImmutable(), 'Mensaje sintético')]));
    $first = $json($http($owners[0], '/listados/documents?length=100&table=1'));
    $second = $json($http($owners[0], '/listados/documents?start=100&length=100&table=1'));
    $check(100 === count($first['data']) && 31 === count($second['data']) && 131 === $first['recordsTotal'], 'SQL pages must cover the complete authorized set.');
    $check(131 === count(array_unique(array_merge(array_column($first['data'], 0), array_column($second['data'], 0)))), 'Stable pages must not overlap.');
    $filtered = $json($http($owners[0], '/listados/documents?q=Tercero%2000&length=5&table=1'));
    $check(10 === $filtered['recordsFiltered'] && 5 === count($filtered['data']), 'Search and totals must apply before LIMIT.');
    $check(0 === $json($http($owners[0], '/listados/documents?table=1&q=%25'))['recordsFiltered'], 'A percent search must be literal, never an SQL wildcard.');
    $check(0 === $json($http($owners[0], '/listados/documents?table=1&q=_'))['recordsFiltered'], 'An underscore search must be literal.');
    $foreign = $json($http($owners[1], '/listados/documents?table=1'));
    $check(1 === $foreign['recordsTotal'] && !str_contains(json_encode($foreign), 'Tercero'), 'Other owners must not leak rows or counts.');
    foreach (['-1', '0', '101', 'all', '99999999999999'] as $limit) {
        $check(400 === $http($owners[0], '/listados/documents?table=1&length='.$limit)->getStatusCode(), 'Unbounded or malformed page sizes must be rejected.');
    }
    foreach (['firms', 'independent', 'invitations', 'clients', 'team', 'inbox'] as $kind) {
        $check(403 === $http($owners[0], '/listados/'.$kind.'?table=1')->getStatusCode(), 'Clients cannot read administrative/advisory catalogs.');
    }
    $inbox = $json($http($admins[0], '/listados/inbox?table=1&section=submitted&length=100'));
    $check(131 === $inbox['recordsTotal'] && 100 === count($inbox['data']), 'Advisory pages must use the same current-client scope.');
    $check(0 === $json($http($admins[1], '/listados/inbox?table=1'))['recordsTotal'], 'Foreign advisory rows must be excluded.');
    $messages = $json($http($owners[0], '/listados/conversations?table=1'));
    $check(1 === $messages['recordsTotal'] && str_contains($messages['data'][0][3], '1 mensajes'), 'Message lists must use SQL unread summaries.');
    $check(200 === $http($owners[0], '/mensajes')->getStatusCode(), 'Message page must render with bounded rows.');
    $apiPage = $json($http($owners[0], '/api/v1/documents?start=100&length=100', api: true));
    $check(31 === count($apiPage['documents']) && 131 === $apiPage['pagination']['total'], 'The API must expose bounded pages and complete metadata.');
    foreach (['csv', 'xlsx'] as $format) {
        $response = $http($owners[0], '/listados/documents/exportar?format='.$format.'&q=Tercero%200');
        if (!$response instanceof BinaryFileResponse) {
            throw new RuntimeException('Expected private export file.');
        }
        $check(200 === $response->getStatusCode(), 'Exports must be private files.');
        $path = $response->getFile()->getPathname();
        try {
            if ('csv' === $format) {
                $lines = file($path);
                $check(101 === count($lines), 'Export must include every filtered row, beyond the visible page.');
            } else {
                $zip = new ZipArchive();
                $check(true === $zip->open($path), 'Excel must be valid XLSX.');
                $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
                $zip->close();
                $check(101 === substr_count($xml, '<row '), 'Excel must include every filtered row.');
            }
        } finally {
            unlink($path);
        }
    }
    $third = $http($owners[0], '/listados/documents/exportar?format=csv');
    if (!$third instanceof BinaryFileResponse) {
        throw new RuntimeException('Expected third export file.');
    }
    $check(200 === $third->getStatusCode(), 'Three exports must be allowed.');
    unlink($third->getFile()->getPathname());
    $check(429 === $http($owners[0], '/listados/documents/exportar?format=csv')->getStatusCode(), 'A fourth export must be rejected.');
    $check(200 === $http($owners[0], '/listados/documents?table=1')->getStatusCode(), 'Export and read budgets must be independent.');
    $options = $json($http($admins[0], '/opciones/shared_documents?owner='.$owners[0]->id().'&start=100&length=25'));
    $check(25 === count($options['data']) && 131 === $options['total'], 'Shared-document selectors must paginate before rendering.');
    $check(403 === $http($admins[1], '/opciones/shared_documents?owner='.$owners[0]->id())->getStatusCode(), 'Forged selector owners must not cross advisories.');
    $check(200 === $http($admins[0], '/mensajes/nuevo?owner='.$owners[0]->id())->getStatusCode(), 'The message form must render paginated shared-document choices.');
    $superadmin = new User(Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122().'@example.test', 'unused-hash', Role::Superadmin, null, true);
    (new DoctrineUserRepository($em))->save($superadmin);
    $check(403 === $http($superadmin, '/listados/documents?table=1')->getStatusCode(), 'Superadmins must never gain document access.');
    $check(403 === $http($superadmin, '/listados/inbox/exportar?format=csv')->getStatusCode(), 'Superadmins must never export advisory documents.');
    $period = new App\Finanzas\Domain\QuarterPeriod(2026, 4);
    $db->executeStatement("UPDATE client_document SET status='validated' WHERE client_user_id=?", [$owners[0]->id()]);
    $open = $json($http($owners[0], '/listados/movements?table=1&year=2026&quarter=4&length=100'));
    $check(131 === $open['recordsTotal'] && 100 === count($open['data']), 'Quarter movements must paginate in SQL without reducing totals.');
    $records = (new App\Finanzas\Infrastructure\Persistence\DoctrineFinancialRecords($em))->forClient($owners[0]->id(), $firms[0], $period);
    $summary = (new App\Finanzas\Domain\QuarterCalculation($owners[0]->id(), $firms[0], $period, $records))->summary;
    $check(1_310_000 === $summary->incomeCents(), 'The financial calculation must include all 131 rows, not a page.');
    (new App\Finanzas\Infrastructure\Persistence\DoctrineQuarterRepository($em))->save(App\Finanzas\Domain\Quarter::firstClose($owners[0]->id(), $firms[0], $period, $summary, $admins[0]->id(), new DateTimeImmutable()));
    $closed = $json($http($owners[0], '/listados/movements?table=1&year=2026&quarter=4&start=100&length=100'));
    $check(131 === $closed['recordsTotal'] && 31 === count($closed['data']), 'Saved snapshots must paginate through JSON_TABLE.');
    $db->executeStatement("UPDATE client_document SET party_name='Changed after close' WHERE client_user_id=?", [$owners[0]->id()]);
    $snapshotPage = $json($http($owners[0], '/listados/movements?table=1&year=2026&quarter=4&q=Tercero'));
    $check(131 === $snapshotPage['recordsFiltered'], 'Closed pages must read the snapshot, never later live data.');
    $check(404 === $http($admins[1], '/listados/movements?table=1&year=2026&quarter=4&client='.$db->fetchOne('SELECT id FROM client_profile WHERE user_id=?', [$owners[0]->id()]))->getStatusCode(), 'Quarter pagination must preserve advisory access checks.');
    $check(200 === $http($owners[0], '/inicio')->getStatusCode(), 'Home must render SQL message counts and a bounded document preview.');
    $container->get('cache.resource_limits')->clear();
    $snapshot = (int) $db->fetchOne('SELECT COUNT(*) FROM client_document');
    $usageBefore = (int) $db->fetchOne('SELECT COUNT(*) FROM document_usage WHERE owner_id=?', [$owners[0]->id()]);
    for ($i = 0; $i < 10; ++$i) {
        $response = $http($owners[0], 0 === $i % 2 ? '/documentos/nuevo' : '/api/v1/documents', 'POST', 1 === $i % 2);
        $check(429 !== $response->getStatusCode(), 'Web and API share ten upload attempts.');
    }
    $blocked = $http($owners[0], '/api/v1/documents', 'POST', true);
    $check(429 === $blocked->getStatusCode() && (int) $blocked->headers->get('Retry-After') > 0, 'The eleventh combined upload must be rejected before file processing.');
    $check(429 !== $http($owners[1], '/api/v1/documents', 'POST', true)->getStatusCode(), 'Another owner/firm has its own allowance.');
    $check($snapshot === (int) $db->fetchOne('SELECT COUNT(*) FROM client_document'), 'Blocked or invalid uploads must not create records.');
    $check($usageBefore === (int) $db->fetchOne('SELECT COUNT(*) FROM document_usage WHERE owner_id=?', [$owners[0]->id()]), 'A rejected attempt must not consume monthly quota.');
} finally {
    $db->rollBack();
    $em->clear();
    $kernel->shutdown();
    $check($before === (int) $db->fetchOne('SELECT COUNT(*) FROM client_document'), 'Synthetic fixtures must roll back.');
}
echo 'Resource limits integration checks: '.$checks." passed.\nSynthetic fixtures rolled back; no mail, AI or uploaded files.\n";
