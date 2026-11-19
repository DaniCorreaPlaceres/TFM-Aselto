<?php

require dirname(__DIR__).'/bootstrap.php';

use App\Asesoria\Domain\Firm;
use App\Asesoria\Infrastructure\Persistence\DoctrineFirmRepository;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\FirmLinkNotifier;
use App\Cliente\Infrastructure\Persistence\DoctrineClientRepository;
use App\Documento\Application\DocumentForm;
use App\Documento\Domain\Document;
use App\Documento\Infrastructure\Persistence\DoctrineDocumentRepository;
use App\Finanzas\Domain\Quarter;
use App\Finanzas\Domain\QuarterCalculation;
use App\Finanzas\Domain\QuarterPeriod;
use App\Finanzas\Infrastructure\Persistence\DoctrineQuarterRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Kernel;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class LinkRecordingNotifier implements FirmLinkNotifier
{
    /** @var list<string> */
    public static array $tokens = [];

    public function send(string $email, string $firmName, string $token): void
    {
        self::$tokens[] = $token;
    }
}

final class LinkCheckKernel extends Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->setDefinition(LinkRecordingNotifier::class, new Definition(LinkRecordingNotifier::class));
                $container->setAlias(FirmLinkNotifier::class, LinkRecordingNotifier::class);
                $container->getDefinition('security.token_storage')->setPublic(true);
            }
        });
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/firm-link-check';
    }
}

$kernel = new LinkCheckKernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
if (!str_ends_with((string) $db->fetchOne('SELECT DATABASE()'), '_security_ci')) {
    throw new RuntimeException('This workflow requires an explicitly disposable *_security_ci database.');
}
$uuid = static fn (): string => Uuid::v4()->toRfc4122();
$firm = $uuid();
$otherFirm = $uuid();
$prefix = $uuid();
$password = 'synthetic-password';
$users = new DoctrineUserRepository($em);
$clients = new DoctrineClientRepository($em);
$accounts = [];
$documents = [];
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
};
$session = new Session(new MockArraySessionStorage());
$http = static function (string $path, string $method = 'GET', array $data = [], ?User $actor = null, ?string $jwt = null) use ($kernel, $session): Response {
    $kernel->shutdown();
    $kernel->boot();
    $kernel->getContainer()->get('security.token_storage')->setToken(null);
    $session->remove('_security_main');
    if (null !== $actor) {
        $security = SecurityUser::fromDomain($actor);
        $session->set('_security_main', serialize(new UsernamePasswordToken($security, 'main', $security->getRoles())));
        App\Tests\Support\AuthenticatedSession::start($session);
    }
    $api = str_starts_with($path, '/api/');
    $server = $api ? ['CONTENT_TYPE' => 'application/json'] : [];
    if (null !== $jwt) {
        $server['HTTP_AUTHORIZATION'] = 'Bearer '.$jwt;
    }
    $request = Request::create('http://localhost'.$path, $method, $api ? [] : $data, server: $server, content: $api ? json_encode($data, JSON_THROW_ON_ERROR) : null);
    $request->setSession($session);
    $request->cookies->set($session->getName(), $session->getId());
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
};
$csrf = static function (Response $response): string {
    preg_match_all('/name="_csrf_token" value="([^"]+)"/', (string) $response->getContent(), $match);

    return html_entity_decode(end($match[1]) ?: '', ENT_QUOTES);
};
$login = static function (User $user) use ($http, $password): string {
    $response = $http('/api/v1/auth/login', 'POST', ['email' => $user->email(), 'password' => $password]);

    return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'] ?? throw new RuntimeException('Synthetic login failed.');
};

try {
    foreach ([$firm, $otherFirm] as $id) {
        (new DoctrineFirmRepository($em))->save(new Firm($id, 'Asesoría ficticia '.$id));
    }
    foreach (['root' => Role::Superadmin, 'admin' => Role::FirmAdmin, 'advisor' => Role::Advisor, 'foreign' => Role::Advisor, 'owner' => Role::Client, 'other' => Role::Client] as $name => $role) {
        $firmId = match ($name) {
            'admin', 'advisor' => $firm, 'foreign' => $otherFirm, default => null,
        };
        $accounts[$name] = new User($uuid(), $prefix.'-'.$name.'@example.test', password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]), $role, $firmId, true);
        $users->save($accounts[$name]);
        if (Role::Client === $role) {
            $clients->save(new Client($uuid(), $accounts[$name]->id(), ClientKind::Company, 'Empresa ficticia '.$name, null));
        }
    }
    $owner = $accounts['owner'];
    foreach (['closed' => '2026-03-01', 'open' => '2026-11-01'] as $key => $date) {
        $document = Document::receive($uuid(), $owner->id(), null, $key.'.pdf', 'synthetic/'.$key.'.pdf', 'application/pdf', new DateTimeImmutable())->confirm(DocumentForm::read('gasto', $date, 'Tercero ficticio', '00000000T', '100', '21'), new DateTimeImmutable());
        (new DoctrineDocumentRepository($em))->save($document);
        $documents[$key] = $document;
    }
    $personalPeriod = new QuarterPeriod(2026, 1);
    $personalSummary = (new QuarterCalculation($owner->id(), null, $personalPeriod, (new App\Finanzas\Infrastructure\Persistence\DoctrineFinancialRecords($em))->forClient($owner->id(), null, $personalPeriod)))->summary;
    (new DoctrineQuarterRepository($em))->save(Quarter::firstClose($owner->id(), null, $personalPeriod, $personalSummary, $owner->id(), new DateTimeImmutable()));
    $snapshot = $db->fetchAllAssociative('SELECT * FROM financial_quarter WHERE owner_id = ?', [$owner->id()]);
    $closedBefore = $db->fetchAssociative('SELECT * FROM client_document WHERE id = ?', [$documents['closed']->id()]);
    $openBefore = $db->fetchAssociative('SELECT * FROM client_document WHERE id = ?', [$documents['open']->id()]);
    $oldJwt = $login($owner);
    $foreignJwt = $login($accounts['foreign']);
    $adminJwt = $login($accounts['admin']);
    $competing = Doctrine\DBAL\DriverManager::getConnection($db->getParams());
    $competing->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
    $competing->beginTransaction();
    try {
        (new App\Shared\Infrastructure\DoctrineClientTransaction($em))->run($owner->id(), static function () use ($competing, $owner, $check): void {
            try {
                $competing->executeQuery('SELECT id FROM client_profile WHERE user_id = ? FOR UPDATE', [$owner->id()]);
                $check(false, 'The shared client lock must block a competing mutation.');
            } catch (Doctrine\DBAL\Exception\LockWaitTimeoutException) {
                $check(true, 'A competing connection cannot mutate while linking holds the client lock.');
            }
        });
    } finally {
        $competing->rollBack();
        $competing->close();
    }
    $check(302 === $http('/clientes/vincular')->getStatusCode(), 'Guests must log in.');
    foreach (['root', 'owner'] as $name) {
        $check(403 === $http('/clientes/vincular', actor: $accounts[$name])->getStatusCode(), 'Other roles cannot send invitations.');
    }
    $check(200 === $http('/clientes/vincular', actor: $accounts['advisor'])->getStatusCode(), 'Advisor can open invitations.');
    $check(422 === $http('/clientes/vincular', 'POST', ['email' => $owner->email()], $accounts['advisor'])->getStatusCode(), 'Sending requires CSRF.');
    $page = $http('/clientes/vincular', actor: $accounts['advisor']);
    $sent = $http('/clientes/vincular', 'POST', ['email' => $owner->email(), '_csrf_token' => $csrf($page)], $accounts['advisor']);
    $check(302 === $sent->getStatusCode(), 'Advisor can send invitations: '.$sent->getStatusCode().' '.substr(strip_tags((string) $sent->getContent()), -1800));
    $first = LinkRecordingNotifier::$tokens[0];
    $check(null === $db->fetchOne('SELECT firm_id FROM client_profile WHERE user_id = ?', [$owner->id()]), 'Sending never grants membership.');
    $check(404 === $http('/api/v1/advisory/documents/'.$documents['open']->id(), jwt: $adminJwt)->getStatusCode(), 'Documents stay private before acceptance.');
    $check(201 === $http('/api/v1/firm-link-invitations', 'POST', ['email' => $owner->email()], jwt: $adminJwt)->getStatusCode(), 'Admin can resend through the shared API case.');
    $token = end(LinkRecordingNotifier::$tokens);
    $check(404 === $http('/vincular-asesoria/'.$first, actor: $owner)->getStatusCode(), 'Resend invalidates previous URL.');
    $check(404 === $http('/vincular-asesoria/'.$token, actor: $accounts['other'])->getStatusCode(), 'Only the invited account may accept.');
    $page = $http('/vincular-asesoria/'.$token, actor: $owner);
    $check(200 === $page->getStatusCode() && str_contains((string) $page->getContent(), 'Aceptar y compartir'), 'Acceptance explains data sharing.');
    $check(422 === $http('/vincular-asesoria/'.$token, 'POST', [], $owner)->getStatusCode(), 'Acceptance requires CSRF.');
    $check(200 === $http('/api/v1/firm-link-invitations/accept', 'POST', ['token' => $token], jwt: $oldJwt)->getStatusCode(), 'The recipient may explicitly accept via API.');
    $check($firm === $db->fetchOne('SELECT firm_id FROM identity_user WHERE id = ?', [$owner->id()]), 'Account is linked.');
    $check($firm === $db->fetchOne('SELECT firm_id FROM client_profile WHERE user_id = ?', [$owner->id()]), 'Profile is linked atomically.');
    $check(401 === $http('/api/v1/me', jwt: $oldJwt)->getStatusCode(), 'Previous JWT is invalidated.');
    $check(302 === $http('/mi-asesoria', actor: $owner)->getStatusCode(), 'Previous web session is invalidated.');
    $check($snapshot === $db->fetchAllAssociative('SELECT * FROM financial_quarter WHERE owner_id = ?', [$owner->id()]), 'Personal closure bytes remain unchanged.');
    $check($closedBefore === $db->fetchAssociative('SELECT * FROM client_document WHERE id = ?', [$documents['closed']->id()]), 'Closed personal documents remain unchanged.');
    $openAfter = $db->fetchAssociative('SELECT * FROM client_document WHERE id = ?', [$documents['open']->id()]);
    $check($firm === $openAfter['firm_id'] && 'submitted' === $openAfter['status'], 'Open records are pending professional review.');
    unset($openBefore['firm_id'], $openBefore['version'], $openAfter['firm_id'], $openAfter['version']);
    $check($openBefore === $openAfter, 'Original data, confirmation history and file references are retained.');
    foreach ($documents as $document) {
        $check(200 === $http('/api/v1/advisory/documents/'.$document->id(), jwt: $adminJwt)->getStatusCode(), 'Linked firm can consult open and historical records.');
        $check(404 === $http('/api/v1/advisory/documents/'.$document->id(), jwt: $foreignJwt)->getStatusCode(), 'Unrelated firm remains denied.');
    }
    $page = $http('/bandeja?status=personal', actor: $accounts['advisor']);
    $check(200 === $page->getStatusCode() && str_contains((string) $page->getContent(), $documents['closed']->id()) && !str_contains((string) $page->getContent(), $documents['open']->id()), 'Personal history has its own listing.');
    $page = $http('/bandeja/'.$documents['closed']->id(), actor: $accounts['advisor']);
    $check(200 === $page->getStatusCode() && !str_contains((string) $page->getContent(), 'Validar registro'), 'Historical detail has no validation action.');
    $newJwt = $login($owner);
    $quarterResponse = $http('/api/v1/quarters/2026/4', jwt: $newJwt);
    $quarterData = json_decode((string) $quarterResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);
    $check(200 === $quarterResponse->getStatusCode() && 0 === $quarterData['summary']['expense_cents'] && $documents['open']->id() === ($quarterData['pending'][0]['id'] ?? null), 'Confirmed open records appear as pending, outside professionally validated figures.');
    $check(409 === $http('/api/v1/documents/'.$documents['closed']->id().'/amend', 'POST', ['version' => $documents['closed']->version()], jwt: $newJwt)->getStatusCode(), 'Personal closed records still require reopening after linking.');
    $clientId = $db->fetchOne('SELECT id FROM client_profile WHERE user_id = ?', [$owner->id()]);
    $check(404 === $http('/api/v1/advisory/clients/'.$clientId.'/quarters/2026/1/reopen?scope=personal', 'POST', ['version' => 1], jwt: $adminJwt)->getStatusCode(), 'The firm cannot reopen a personal closure.');
    $check(200 === $http('/api/v1/quarters/2026/1/reopen?scope=personal', 'POST', ['version' => 1], jwt: $newJwt)->getStatusCode(), 'The owner can reopen the historical personal closure.');
    $check(200 === $http('/api/v1/documents/'.$documents['closed']->id().'/amend', 'POST', ['version' => $documents['closed']->version()], jwt: $newJwt)->getStatusCode(), 'Correction enters professional review workflow after personal reopening.');
    $check($firm === $db->fetchOne('SELECT firm_id FROM client_document WHERE id = ?', [$documents['closed']->id()]), 'A corrected historical record joins the current firm, retaining its personal closure history.');
    $check(404 === $http('/api/v1/firm-link-invitations/accept', 'POST', ['token' => $token], jwt: $newJwt)->getStatusCode(), 'Accepted invitations cannot be reused.');
    $check(404 === $http('/api/v1/firm-link-invitations', 'POST', ['email' => $owner->email()], jwt: $foreignJwt)->getStatusCode(), 'A linked client cannot be transferred by another firm.');
    $check(201 === $http('/api/v1/firm-link-invitations', 'POST', ['email' => $accounts['other']->email()], jwt: $adminJwt)->getStatusCode(), 'A second independent account can be invited.');
    $otherToken = end(LinkRecordingNotifier::$tokens);
    $page = $http('/vincular-asesoria/'.$otherToken, actor: $accounts['other']);
    $accepted = $http('/vincular-asesoria/'.$otherToken, 'POST', ['_csrf_token' => $csrf($page)], $accounts['other']);
    $check(200 === $accepted->getStatusCode() && str_contains((string) $accepted->getContent(), 'Tu asesoría está vinculada'), 'Web acceptance completes and asks the client to log in again.');
    $check($firm === $db->fetchOne('SELECT firm_id FROM identity_user WHERE id = ?', [$accounts['other']->id()]), 'Web and API acceptance persist the same membership.');
} finally {
    $kernel->shutdown();
    foreach ($accounts as $user) {
        $db->executeStatement('DELETE FROM firm_link_invitation WHERE owner_id = ?', [$user->id()]);
        $db->executeStatement('DELETE FROM client_document WHERE client_user_id = ?', [$user->id()]);
        $db->executeStatement('DELETE FROM financial_quarter WHERE owner_id = ?', [$user->id()]);
        $db->executeStatement('DELETE FROM quarter_update_outbox WHERE owner_id = ?', [$user->id()]);
        $db->executeStatement('DELETE FROM client_profile WHERE user_id = ?', [$user->id()]);
        $db->executeStatement('DELETE FROM identity_user WHERE id = ?', [$user->id()]);
    }
    foreach ([$firm, $otherFirm] as $id) {
        $db->executeStatement('DELETE FROM advisory_firm WHERE id = ?', [$id]);
    }
}
echo $checks." firm link checks passed.\n";
