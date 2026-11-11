<?php

// Explicit local integration check. Uses committed synthetic fixtures to test real SSE delivery.
require dirname(__DIR__).'/bootstrap.php';

use App\Asesoria\Domain\Firm;
use App\Asesoria\Infrastructure\Persistence\DoctrineFirmRepository;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Infrastructure\Persistence\DoctrineClientRepository;
use App\Documento\Application\DocumentForm;
use App\Documento\Domain\Document;
use App\Documento\Infrastructure\Persistence\DoctrineDocumentRepository;
use App\Finanzas\Infrastructure\Realtime\NotifyingDocumentRepository;
use App\Finanzas\Infrastructure\Realtime\QuarterTopic;
use App\Finanzas\Infrastructure\Realtime\QuarterUpdateOutbox;
use App\Finanzas\Infrastructure\Realtime\QuarterUpdatePublisher;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Kernel;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

$kernel = new Kernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$before = hash('sha256', json_encode([$db->fetchAllAssociative('SELECT * FROM client_document ORDER BY id'), $db->fetchAllAssociative('SELECT * FROM financial_quarter ORDER BY owner_id, scope, year, number')], JSON_THROW_ON_ERROR));
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void { if (!$condition) { throw new RuntimeException($message); } ++$checks; };
$uuid = static fn (): string => Uuid::v4()->toRfc4122();
$firmId = $uuid();
$otherFirmId = $uuid();
$ownerId = $uuid();
$adminId = $uuid();
$otherId = $uuid();
$rootId = $uuid();
$inactiveId = $uuid();
$profileId = $uuid();
$documentId = $uuid();
$streams = [];
$secret = (string) getenv('MERCURE_JWT_SECRET');
if ('' === $secret) {
    throw new RuntimeException('The local hub secret is required.');
}
$factory = new LcobucciFactory($secret);
$jwt = $factory->create([new Grant([Grant::ACTION_PUBLISH], ['*'])], ['iss' => 'https://localhost', 'sub' => 'integration', 'client_id' => 'integration', 'aud' => ['http://localhost:8080/.well-known/mercure', 'http://mercure/.well-known/mercure'], 'exp' => new DateTimeImmutable('+60 seconds')]);
$provider = new class($jwt) implements TokenProviderInterface {
    public function __construct(private readonly string $token)
    {
    }

    public function getJwt(): string
    {
        return $this->token;
    }
};
$hub = new Hub('http://mercure/.well-known/mercure', $provider, $factory, 'http://localhost:8080/.well-known/mercure', HttpClient::create(['timeout' => 3]), 'mercure_access_token');
$fakeHub = new class($hub) implements HubInterface {
    /** @var list<Update> */
    public array $updates = [];
    public bool $fail = true;
    public ?Closure $duringPublish = null;

    public function __construct(private readonly HubInterface $hub)
    {
    }

    public function getPublicUrl(): string
    {
        return $this->hub->getPublicUrl();
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return $this->hub->getFactory();
    }

    public function getProtocolVersion(): ProtocolVersion
    {
        return $this->hub->getProtocolVersion();
    }

    public function getCookieName(): string
    {
        return $this->hub->getCookieName();
    }

    public function publish(Update $update): string
    {
        if ($this->fail) {
            throw new RuntimeException('Synthetic hub outage.');
        }
        $this->updates[] = $update;
        if (null !== $this->duringPublish) {
            ($this->duringPublish)();
            $this->duringPublish = null;
        }

        return 'synthetic';
    }
};

try {
    $users = new DoctrineUserRepository($em);
    $clients = new DoctrineClientRepository($em);
    $documents = new DoctrineDocumentRepository($em);
    $firms = new DoctrineFirmRepository($em);
    $db->beginTransaction();
    $firms->save(new Firm($firmId, 'Asesoría sintética en vivo'));
    $firms->save(new Firm($otherFirmId, 'Otra asesoría sintética en vivo'));
    $owner = new User($ownerId, $uuid().'@example.test', 'synthetic-hash', Role::Client, $firmId, true);
    $admin = new User($adminId, $uuid().'@example.test', 'synthetic-hash', Role::FirmAdmin, $firmId, true);
    $other = new User($otherId, $uuid().'@example.test', 'synthetic-hash', Role::Advisor, $otherFirmId, true);
    $root = new User($rootId, $uuid().'@example.test', 'synthetic-hash', Role::Superadmin, null, true);
    $inactive = new User($inactiveId, $uuid().'@example.test', 'synthetic-hash', Role::Advisor, $firmId, false);
    foreach ([$owner, $admin, $other, $root, $inactive] as $user) {
        $users->save($user);
    }
    $clients->save(new Client($profileId, $ownerId, ClientKind::SelfEmployed, 'Cliente sintético en vivo', $firmId));
    $document = Document::receive($documentId, $ownerId, $firmId, 'synthetic.pdf', 'synthetic.pdf', 'application/pdf', new DateTimeImmutable())->saveDraft(DocumentForm::read('ingreso', '2026-11-01', 'Ejemplo', '00000000T', '100', '21'));
    $documents->save($document);
    $db->commit();
    $outbox = new QuarterUpdateOutbox($em);
    $notifying = new NotifyingDocumentRepository($documents, $outbox, $em);
    $publisher = new QuarterUpdatePublisher($em, $fakeHub, new NullLogger());
    $db->beginTransaction();
    $notifying->save($document->saveDraft(DocumentForm::read('ingreso', '2026-11-01', 'Ejemplo', '00000000T', '150', '21')));
    $check(0 === $publisher->publish($ownerId), 'An uncommitted transaction must not publish.');
    $db->rollBack();
    $em->clear();
    $check(false === $db->fetchOne('SELECT revision FROM quarter_update_outbox WHERE owner_id = ?', [$ownerId]), 'Rollback must remove the notification.');
    $check(10000 === $documents->find($documentId)->baseCents(), 'Rollback must preserve original financial data.');
    $db->transactional(fn () => $outbox->record($ownerId, $firmId));
    $check(0 === $publisher->publish($ownerId), 'A hub outage must defer delivery without throwing.');
    $check(0 === (int) $db->fetchOne('SELECT published_revision FROM quarter_update_outbox WHERE owner_id = ?', [$ownerId]), 'A failed publication must remain pending.');
    $fakeHub->fail = false;
    $fakeHub->duringPublish = fn () => $db->transactional(fn () => $outbox->record($ownerId, $firmId));
    $check(1 === $publisher->publish($ownerId), 'Retry must publish the committed notification.');
    $check(2 === (int) $db->fetchOne('SELECT revision FROM quarter_update_outbox WHERE owner_id = ?', [$ownerId]) && 1 === (int) $db->fetchOne('SELECT published_revision FROM quarter_update_outbox WHERE owner_id = ?', [$ownerId]), 'A concurrent newer revision must remain pending.');
    $publisher->publish($ownerId);
    $update = $fakeHub->updates[0];
    $check($update->isPrivate() && '{"changed":true}' === $update->getData(), 'The event must be private and contain no fiscal data.');
    $check(in_array(QuarterTopic::forUser($ownerId), $update->getTopics(), true) && in_array(QuarterTopic::forUser($adminId), $update->getTopics(), true), 'Owner and authorized staff must be notified.');
    foreach ([$otherId, $rootId, $inactiveId] as $forbiddenId) {
        $check(!in_array(QuarterTopic::forUser($forbiddenId), $update->getTopics(), true), 'An unauthorized recipient must not be notified.');
    }

    $sessions = [];
    $http = static function (?User $actor, string $url, string $method = 'GET', array $data = [], bool $fragment = false, string $host = 'localhost') use ($kernel, &$sessions): Symfony\Component\HttpFoundation\Response {
        $session = $sessions[$actor?->id() ?? 'anonymous'] ??= new Session(new MockArraySessionStorage());
        if (null !== $actor) {
            $security = SecurityUser::fromDomain($actor);
            $session->set('_security_main', serialize(new UsernamePasswordToken($security, 'main', $security->getRoles())));
        }
        $request = Request::create('http://'.$host.$url, $method, $data);
        $request->setSession($session);
        $request->cookies->set($session->getName(), $session->getId());
        if ($fragment) {
            $request->headers->set('X-Quarter-Fragment', '1');
        }
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    };
    $fields = static function (Symfony\Component\HttpFoundation\Response $response): array {
        preg_match_all('/name="(version|fingerprint|_csrf_token)" value="([^"]*)"/', $response->getContent(), $matches, PREG_SET_ORDER);
        $data = [];
        foreach ($matches as $match) {
            $data[$match[1]] = html_entity_decode($match[2], ENT_QUOTES);
        }

        return $data;
    };
    $subscription = $http($owner, '/trimestre/en-vivo/acceso?topic=untrusted');
    $check(200 === $subscription->getStatusCode(), 'Owner subscription must be authorized.');
    $url = json_decode($subscription->getContent(), true, flags: JSON_THROW_ON_ERROR)['url'];
    $check(str_contains($url, rawurlencode(QuarterTopic::forUser($ownerId))) && !str_contains($url, 'untrusted'), 'The server must choose only the current user topic.');
    $cookie = $subscription->headers->getCookies()[0];
    $check($cookie->isHttpOnly() && '/.well-known/mercure' === $cookie->getPath() && $cookie->getExpiresTime() <= time() + 60, 'Subscription cookies must be private, scoped and short-lived.');
    $check(200 === $http($owner, '/trimestre/en-vivo/acceso', host: '127.0.0.1')->getStatusCode(), 'Both local hostnames must support authorization.');
    $check(403 === $http($root, '/trimestre/en-vivo/acceso')->getStatusCode(), 'Superadmin must not get a subscription.');
    $check(302 === $http(null, '/trimestre/en-vivo/acceso')->getStatusCode(), 'Anonymous users must authenticate.');
    $check(403 === $http($inactive, '/trimestre/en-vivo/acceso')->getStatusCode(), 'Inactive users must not get a subscription.');
    $fragment = $http($owner, '/ingresos?year=2026&quarter=4', fragment: true);
    $check(200 === $fragment->getStatusCode() && !str_contains($fragment->getContent(), '<html') && !str_contains($fragment->getContent(), 'id="quarter-year"'), 'Fragments must omit the page shell and editable period selector.');
    $check(404 === $http($other, '/clientes/'.$profileId.'/trimestre', fragment: true)->getStatusCode(), 'Other firms must not read a fragment.');
    $check(200 === $http($owner, '/inicio', fragment: true)->getStatusCode(), 'Client home must expose its protected financial fragment.');

    $client = HttpClient::create();
    $stream = $client->request('GET', 'http://web'.$url, ['headers' => ['Host' => 'localhost:8080', 'Cookie' => $cookie->getName().'='.$cookie->getValue()], 'buffer' => false, 'timeout' => 3]);
    $streams[] = $stream;
    $check(200 === $stream->getStatusCode(), 'The real hub must accept the subscriber cookie through Nginx: '.$stream->getStatusCode().' '.(200 === $stream->getStatusCode() ? '' : $stream->getContent(false)));
    $otherCookie = $http($other, '/trimestre/en-vivo/acceso')->headers->getCookies()[0];
    $wrong = $client->request('GET', 'http://web'.$url, ['headers' => ['Host' => 'localhost:8080', 'Cookie' => $otherCookie->getName().'='.$otherCookie->getValue()], 'buffer' => false, 'timeout' => 3]);
    $streams[] = $wrong;
    $wrongStatus = $wrong->getStatusCode();
    $check(in_array($wrongStatus, [200, 403], true), 'An unrelated subscriber may only be refused or receive an empty filtered stream.');
    if (403 === $wrongStatus) {
        $wrong->cancel();
    }
    $response = $http($owner, '/documentos/'.$documentId);
    $data = [...$fields($response), 'intent' => 'confirm', 'direction' => 'ingreso', 'issued_on' => '2026-11-01', 'party_name' => 'Ejemplo', 'tax_id' => '00000000T', 'base' => '100', 'tax_rate' => '21'];
    $check(302 === $http($owner, '/documentos/'.$documentId, 'POST', $data)->getStatusCode(), 'A committed web confirmation must succeed.');
    $received = '';
    $forbidden = '';
    $responses = 200 === $wrongStatus ? [$stream, $wrong] : [$stream];
    foreach ($client->stream($responses, 1) as $response => $chunk) {
        if ($chunk->isTimeout()) {
            break;
        }
        if ($chunk->isFirst() || $chunk->isLast()) {
            continue;
        }
        if ($response === $stream) {
            $received .= $chunk->getContent();
        } else {
            $forbidden .= $chunk->getContent();
        }
    }
    $check(str_contains($received, 'data: {"changed":true}'), 'A real private SSE event must arrive after commit.');
    $check(!str_contains($forbidden, 'data:'), 'A different user token must not receive the owner event.');
    foreach ($streams as $response) {
        $response->cancel();
    }
    $response = $http($admin, '/bandeja/'.$documentId);
    $data = [...$data, ...$fields($response)];
    $check(302 === $http($admin, '/bandeja/'.$documentId, 'POST', $data)->getStatusCode(), 'Advisory validation must succeed with notifications enabled.');
    $fragment = $http($owner, '/ingresos?year=2026&quarter=4', fragment: true);
    $check(str_contains($fragment->getContent(), '100,00') && str_contains($fragment->getContent(), 'Pendientes actuales · 0'), 'A refreshed fragment must show validated figures and resolved pending counts.');
    $url = '/clientes/'.$profileId.'/trimestre?year=2026&quarter=4';
    $response = $http($admin, $url);
    $check(302 === $http($admin, $url, 'POST', [...$fields($response), 'intent' => 'close'])->getStatusCode(), 'Closing must work with notification persistence.');
    $fragment = $http($owner, '/ingresos?year=2026&quarter=4', fragment: true);
    $check(str_contains($fragment->getContent(), 'Cerrado:') && str_contains($fragment->getContent(), '100,00'), 'The refreshed fragment must use the frozen snapshot.');
    $response = $http($admin, $url);
    $check(302 === $http($admin, $url, 'POST', [...$fields($response), 'intent' => 'reopen'])->getStatusCode(), 'Reopening must work with notifications.');
    $check(str_contains($http($owner, '/ingresos?year=2026&quarter=4', fragment: true)->getContent(), 'Abierto:'), 'The refreshed fragment must show the reopened state.');
    $logoutPage = $http($owner, '/inicio');
    preg_match('/action="\/salir"[\s\S]*?name="_csrf_token" value="([^"]*)"/', $logoutPage->getContent(), $logoutMatch);
    $logout = $http($owner, '/salir', 'POST', ['_csrf_token' => html_entity_decode($logoutMatch[1], ENT_QUOTES)]);
    $check(302 === $logout->getStatusCode(), 'Logout must redirect normally.');
    $cleared = array_values(array_filter($logout->headers->getCookies(), static fn ($candidate): bool => $candidate->getName() === $cookie->getName()));
    $check(1 === count($cleared) && $cleared[0]->getExpiresTime() < time(), 'Logout must clear the subscriber cookie.');
    $oldRevision = (int) $db->fetchOne('SELECT revision FROM quarter_update_outbox WHERE owner_id = ?', [$ownerId]);
    $http($owner, '/mensajes');
    $check($oldRevision === (int) $db->fetchOne('SELECT revision FROM quarter_update_outbox WHERE owner_id = ?', [$ownerId]), 'Messaging reads must not create financial notifications.');
    echo 'Live quarter integration checks: '.$checks." passed.\n";
} finally {
    foreach ($streams as $response) {
        $response->cancel();
    }
    if ($db->isTransactionActive()) {
        $db->rollBack();
    }
    $em->clear();
    foreach (['quarter_update_outbox', 'financial_quarter'] as $table) {
        $db->executeStatement('DELETE FROM '.$table.' WHERE owner_id = ?', [$ownerId]);
    }
    $db->executeStatement('DELETE FROM client_document WHERE client_user_id = ?', [$ownerId]);
    $db->executeStatement('DELETE FROM client_profile WHERE user_id = ?', [$ownerId]);
    foreach ([$ownerId, $adminId, $otherId, $rootId, $inactiveId] as $id) {
        $db->executeStatement('DELETE FROM identity_user WHERE id = ?', [$id]);
    }
    foreach ([$firmId, $otherFirmId] as $id) {
        $db->executeStatement('DELETE FROM advisory_firm WHERE id = ?', [$id]);
    }
    $after = hash('sha256', json_encode([$db->fetchAllAssociative('SELECT * FROM client_document ORDER BY id'), $db->fetchAllAssociative('SELECT * FROM financial_quarter ORDER BY owner_id, scope, year, number')], JSON_THROW_ON_ERROR));
    $check($before === $after, 'Existing fiscal data changed.');
    $kernel->shutdown();
}
echo "Synthetic fixtures removed; existing fiscal data unchanged.\n";
