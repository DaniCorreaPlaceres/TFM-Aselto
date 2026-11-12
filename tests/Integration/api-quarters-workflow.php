<?php

// Explicit check against the migrated local database; all synthetic fixtures roll back.
require dirname(__DIR__).'/bootstrap.php';

use App\Asesoria\Domain\Firm;
use App\Asesoria\Infrastructure\Persistence\DoctrineFirmRepository;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Infrastructure\Persistence\DoctrineClientRepository;
use App\Documento\Application\DocumentForm;
use App\Documento\Domain\Document;
use App\Documento\Domain\Tax;
use App\Documento\Infrastructure\Persistence\DoctrineDocumentRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ApiQuartersCheckKernel extends Kernel
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
        return parent::getCacheDir().'/api-quarters-check';
    }
}

$kernel = new ApiQuartersCheckKernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$before = hash('sha256', json_encode($db->fetchAllAssociative('SELECT * FROM client_document ORDER BY id'), JSON_THROW_ON_ERROR));
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void { if (!$condition) { throw new RuntimeException($message); } ++$checks; };
$uuid = static fn (): string => Uuid::v4()->toRfc4122();
$db->beginTransaction();
try {
    $users = new DoctrineUserRepository($em);
    $clients = new DoctrineClientRepository($em);
    $documents = new DoctrineDocumentRepository($em);
    $firms = new DoctrineFirmRepository($em);
    $firmId = $uuid();
    $otherFirmId = $uuid();
    $firms->save(new Firm($firmId, 'Asesoría sintética trimestres API'));
    $firms->save(new Firm($otherFirmId, 'Otra asesoría sintética trimestres API'));
    $password = 'synthetic-quarter-password';
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
            $profiles[$name] = new Client($uuid(), $user->id(), ClientKind::SelfEmployed, 'Cliente sintético trimestres API', $firm);
            $clients->save($profiles[$name]);
        }
    }
    $http = static function (string $url, string $method = 'GET', ?array $data = null, ?string $token = null, string $contentType = 'application/json') use ($kernel, $em): Response {
        $em->clear();
        $kernel->getContainer()->get('security.token_storage')->setToken(null);
        $server = ['CONTENT_TYPE' => $contentType];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $request = Request::create('http://localhost'.$url, $method, server: $server, content: null === $data ? null : json_encode($data, JSON_THROW_ON_ERROR));
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
    $url = '/api/v1/quarters/2026/4';
    $firmUrl = '/api/v1/advisory/clients/'.$profiles['linked']->id().'/quarters/2026/4';
    $get = static fn (string $path, string $actor): Response => $http($path, token: $tokens[$actor]);
    $post = static fn (string $path, array $data, string $actor): Response => $http($path, 'POST', $data, $tokens[$actor]);
    $closing = static fn (array $view): array => ['version' => $view['version'], 'fingerprint' => $view['fingerprint']];
    $check(401 === $http($url)->getStatusCode(), 'Quarter requires JWT.');
    foreach (['admin', 'advisor', 'root'] as $actor) {
        $check(403 === $get($url, $actor)->getStatusCode(), 'Client routes require client role.');
    }
    foreach (['personal', 'linked', 'root'] as $actor) {
        $check(403 === $get($firmUrl, $actor)->getStatusCode(), 'Advisory routes require staff role.');
    }
    $check(404 === $get($firmUrl, 'other')->getStatusCode(), 'Other firm cannot read quarter.');
    $check(404 === $get(str_replace($profiles['linked']->id(), $profiles['stranger']->id(), $firmUrl), 'admin')->getStatusCode(), 'Unlinked client is hidden.');
    $check(404 === $get($url.'?scope='.$otherFirmId, 'personal')->getStatusCode(), 'Foreign scope is hidden.');
    foreach (['/api/v1/quarters/2026/0', '/api/v1/quarters/0000/4', $url.'?scope[]=personal', $url.'?owner_id='.$accounts['stranger']->id()] as $bad) {
        $check(400 === $get($bad, 'personal')->getStatusCode(), 'Invalid period or query rejected.');
    }
    $response = $get($url, 'personal');
    $view = $json($response);
    $check(200 === $response->getStatusCode() && str_contains((string) $response->headers->get('Cache-Control'), 'no-store'), 'Private quarter response.');
    $check(0 === $view['version'] && !$view['closed'] && $view['can_manage'] && 'personal' === $view['scope'], 'New personal quarter.');
    foreach ([[], ['version' => -1], ['version' => '0'], ['version' => 0.5], ['version' => true], ['version' => 0, 'fingerprint' => 'bad'], ['version' => 0, 'fingerprint' => str_repeat('a', 64), 'closed' => true]] as $bad) {
        $check(400 === $post($url.'/close', $bad, 'personal')->getStatusCode(), 'Strict close input.');
    }
    $check(415 === $http($url.'/close', 'POST', $closing($view), $tokens['personal'], 'text/plain')->getStatusCode(), 'Close requires JSON.');
    $check(409 === $post($url.'/reopen', ['version' => 0], 'personal')->getStatusCode(), 'Cannot reopen missing quarter.');
    $check(409 === $post($url.'/close', ['version' => 0, 'fingerprint' => str_repeat('a', 64)], 'personal')->getStatusCode(), 'Stale fingerprint blocked.');
    $response = $post($url.'/close', $closing($view), 'personal');
    $closed = $json($response);
    $check(200 === $response->getStatusCode() && $closed['closed'] && 1 === $closed['version'], 'Empty close persists initial version.');
    $check($accounts['personal']->id() === $closed['events'][0]['actor_id'], 'Closure actor recorded.');
    $check(409 === $post($url.'/close', $closing($closed), 'personal')->getStatusCode(), 'Double close rejected.');
    $check(409 === $post($url.'/reopen', ['version' => 0], 'personal')->getStatusCode(), 'Stale version rejected.');
    $response = $post($url.'/reopen', ['version' => 1], 'personal');
    $reopened = $json($response);
    $check(200 === $response->getStatusCode() && !$reopened['closed'] && 2 === $reopened['version'] && 2 === count($reopened['events']), 'Reopen reloads persisted version and history.');
    $check(409 === $post($url.'/reopen', ['version' => 2], 'personal')->getStatusCode(), 'Double reopen rejected.');
    $make = static function (string $owner, ?string $firm, ?string $date, Tax $tax = Tax::Vat, bool $validated = false) use ($documents, $uuid, $accounts): string {
        $document = Document::receive($uuid(), $owner, $firm, 'synthetic.pdf', 'synthetic-api-quarter/no-file.pdf', 'application/pdf', new DateTimeImmutable('2026-11-01'), $tax);
        if (null !== $date) {
            $details = DocumentForm::read('gasto', $date, 'Proveedor sintético', '00000000T', '100', match ($tax) {
                Tax::Vat => '21', Tax::Igic => '7', Tax::Ipsi => '3',
            }, $tax);
            $document = $document->confirm($details);
            if ($validated) {
                $document = $document->validate($details, $accounts['admin']->id(), new DateTimeImmutable('2026-11-02'));
            }
        }
        $documents->save($document);

        return $document->id();
    };
    $make($accounts['personal']->id(), null, '2026-10-01');
    $make($accounts['personal']->id(), null, '2026-12-31', Tax::Igic);
    $make($accounts['personal']->id(), null, '2026-11-01', Tax::Ipsi);
    $make($accounts['personal']->id(), null, '2027-01-01');
    $view = $json($get($url, 'personal'));
    $check(30000 === $view['summary']['expense_cents'] && -30000 === $view['summary']['result_cents'] && 3 === count($view['summary']['movements']), 'Period boundaries and totals.');
    $check(2100 === $view['summary']['taxes']['iva']['input'] && 700 === $view['summary']['taxes']['igic']['input'] && 300 === $view['summary']['taxes']['ipsi']['input'], 'Separate tax quotas in cents.');
    $check(409 === $post($url.'/close', $closing($reopened), 'personal')->getStatusCode(), 'Document change invalidates fingerprint.');
    $closed = $json($post($url.'/close', $closing($view), 'personal'));
    $check($closed['closed'] && 3 === $closed['version'] && 30000 === $closed['summary']['expense_cents'], 'Closing includes current figures.');
    $late = $make($accounts['personal']->id(), null, null);
    $view = $json($get($url, 'personal'));
    $check(30000 === $view['summary']['expense_cents'] && 1 === count($view['pending']) && null === $view['pending'][0]['issued_on'], 'Late undated document does not change frozen figures.');
    $reopened = $json($post($url.'/reopen', ['version' => 3], 'personal'));
    $check(422 === $post($url.'/close', $closing($reopened), 'personal')->getStatusCode(), 'Undated document blocks close.');
    $db->executeStatement('DELETE FROM client_document WHERE id = ?', [$late]);
    $view = $json($get($url, 'personal'));
    $closed = $json($post($url.'/close', $closing($view), 'personal'));
    $check(5 === $closed['version'] && 5 === count($closed['events']) && 0 === $closed['events'][0]['snapshot']['expense_cents'], 'History snapshots remain immutable.');
    $make($accounts['linked']->id(), $firmId, '2026-11-01');
    $view = $json($get($firmUrl, 'admin'));
    $check(0 === $view['summary']['expense_cents'] && 1 === count($view['pending']), 'Submitted firm document remains pending.');
    $check(422 === $post($firmUrl.'/close', $closing($view), 'advisor')->getStatusCode(), 'Dated pending document blocks advisor close.');
    $linked = $json($get($url, 'linked'));
    $check(!$linked['can_manage'] && $firmId === $linked['scope'], 'Linked client reads current advisory scope.');
    $check(404 === $post($url.'/close', $closing($linked), 'linked')->getStatusCode(), 'Linked client cannot close firm quarter.');
    $db->executeStatement("UPDATE client_document SET status = 'validated' WHERE client_user_id = ?", [$accounts['linked']->id()]);
    $view = $json($get($firmUrl, 'advisor'));
    $check(10000 === $view['summary']['expense_cents'] && [] === $view['pending'], 'Advisor sees validated figures.');
    $closedFirm = $json($post($firmUrl.'/close', $closing($view), 'advisor'));
    $check($closedFirm['closed'] && $accounts['advisor']->id() === $closedFirm['events'][0]['actor_id'], 'Advisor closes within own firm.');
    $check(404 === $post($firmUrl.'/reopen', ['version' => 1], 'other')->getStatusCode(), 'Other firm cannot reopen.');
    $check(200 === $post($firmUrl.'/reopen', ['version' => 1], 'admin')->getStatusCode(), 'Admin reopens own firm quarter.');
    // Simulate linkage after personal closures; preserve the existing personal scope and history.
    $db->executeStatement('UPDATE identity_user SET firm_id = ? WHERE id = ?', [$firmId, $accounts['personal']->id()]);
    $db->executeStatement('UPDATE client_profile SET firm_id = ? WHERE user_id = ?', [$firmId, $accounts['personal']->id()]);
    $personalHistoryUrl = '/api/v1/advisory/clients/'.$profiles['personal']->id().'/quarters/2026/4?scope=personal';
    $history = $json($get($personalHistoryUrl, 'admin'));
    $check(!$history['can_manage'] && 30000 === $history['summary']['expense_cents'] && 5 === count($history['events']), 'Staff reads personal history without converting it.');
    $check(404 === $post(str_replace('?scope=personal', '/reopen?scope=personal', $personalHistoryUrl), ['version' => 5], 'advisor')->getStatusCode(), 'Staff cannot reopen personal closure.');
    $response = $post($url.'/reopen?scope=personal', ['version' => 5], 'personal');
    $check(200 === $response->getStatusCode() && 6 === $json($response)['version'], 'Owner can reopen personal closure after linkage.');
    $view = $json($response);
    $check(200 === $post($url.'/close?scope=personal', $closing($view), 'personal')->getStatusCode(), 'Owner can reclose personal scope after linkage.');
    $check(404 === $get($personalHistoryUrl, 'other')->getStatusCode(), 'Personal history hidden from other firm.');
} finally {
    $db->rollBack();
    $em->clear();
    $kernel->shutdown();
}
$after = hash('sha256', json_encode($db->fetchAllAssociative('SELECT * FROM client_document ORDER BY id'), JSON_THROW_ON_ERROR));
$check($before === $after, 'Existing documents unchanged.');
echo "API quarter integration checks: $checks passed.\nSynthetic users, documents and quarters rolled back; no files created.\n";
