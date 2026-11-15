<?php

// Real kernel with synthetic fixtures and a controlled clock; no waiting, mail or AI.
require dirname(__DIR__).'/bootstrap.php';

use App\Asesoria\Domain\Firm;
use App\Asesoria\Infrastructure\Persistence\DoctrineFirmRepository;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Infrastructure\Persistence\DoctrineClientRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Identidad\Infrastructure\Security\AuthenticationRateLimits;
use App\Identidad\Infrastructure\Security\WebSessionLifetime;
use App\Kernel;
use App\Shared\Domain\Clock;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Uid\Uuid;

final class SessionExpirationTestClock implements Clock
{
    public int $timestamp;

    public function __construct()
    {
        $this->timestamp = time();
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$this->timestamp);
    }
}

final class SessionExpirationCheckKernel extends Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->register(SessionExpirationTestClock::class, SessionExpirationTestClock::class)->setPublic(true);
                $container->setAlias(Clock::class, SessionExpirationTestClock::class);
                $container->register('cache.authentication_limits', ArrayAdapter::class)->setPublic(true);
                $container->getDefinition('security.token_storage')->setPublic(true);
                $container->getDefinition(AuthenticationRateLimits::class)->setPublic(true);
            }
        });
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/session-expiration-check';
    }
}

$kernel = new SessionExpirationCheckKernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$clock = $container->get(SessionExpirationTestClock::class);
$em = $container->get('doctrine')->getManager();
$db = $em->getConnection();
$before = hash('sha256', json_encode($db->fetchAllAssociative('SELECT id, auth_version FROM identity_user ORDER BY id'), JSON_THROW_ON_ERROR));
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void { if (!$condition) { throw new RuntimeException($message); } ++$checks; };
$session = new Session(new MockArraySessionStorage());
$http = static function (string $path, string $method = 'GET', array $data = [], array $headers = [], ?string $token = null) use ($kernel, $container, $em, &$session): Response {
    $em->clear();
    $container->get('security.token_storage')->setToken(null);
    $api = str_starts_with($path, '/api/');
    $request = Request::create('http://localhost'.$path, $method, $api ? [] : $data, server: ['CONTENT_TYPE' => $api ? 'application/json' : 'application/x-www-form-urlencoded'], content: $api ? json_encode($data, JSON_THROW_ON_ERROR) : null);
    $request->headers->add($headers);
    if (null !== $token) {
        $request->headers->set('Authorization', 'Bearer '.$token);
    }
    $request->setSession($session);
    $request->cookies->set($session->getName(), $session->getId());
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
};
$csrf = static function (Response $response): string {
    preg_match('/name="_csrf_token" value="([^"]+)"/', $response->getContent(), $match);

    return html_entity_decode($match[1] ?? throw new RuntimeException('Missing synthetic CSRF.'), ENT_QUOTES);
};
$db->beginTransaction();
try {
    $firmId = Uuid::v4()->toRfc4122();
    (new DoctrineFirmRepository($em))->save(new Firm($firmId, 'Asesoría sintética de sesiones'));
    $user = new User(Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122().'@example.test', password_hash('synthetic web session password', PASSWORD_BCRYPT, ['cost' => 4]), Role::Advisor, $firmId, true);
    (new DoctrineUserRepository($em))->save($user);
    $owner = new User(Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122().'@example.test', 'synthetic-hash', Role::Client, $firmId, true);
    (new DoctrineUserRepository($em))->save($owner);
    $profileId = Uuid::v4()->toRfc4122();
    (new DoctrineClientRepository($em))->save(new Client($profileId, $owner->id(), ClientKind::SelfEmployed, 'Cliente sintético de sesiones', $firmId));
    $login = static function (bool $fresh = true) use (&$session, $http, $csrf, $check, $user, $container, $clock): void {
        if ($fresh) {
            $session = new Session(new MockArraySessionStorage());
        }
        $container->get('cache.authentication_limits')->clear(); // Test-only in-memory pool.
        $form = $http('/entrar');
        $oldId = $session->getId();
        $response = $http('/entrar', 'POST', ['email' => $user->email(), 'password' => 'synthetic web session password', '_csrf_token' => $csrf($form)]);
        $check(302 === $response->getStatusCode() && $session->has('_security_main'), 'A real form login must create an authenticated session.');
        $check($oldId !== $session->getId(), 'Successful login must preserve Symfony session-id rotation.');
        $check(['started_at' => $clock->timestamp, 'last_activity_at' => $clock->timestamp] === $session->get(WebSessionLifetime::KEY), 'Only a successful web login must establish server-side timestamps.');
    };
    $base = $clock->timestamp;
    $login();
    $clock->timestamp = $base + 1799;
    $check(200 === $http('/cuenta')->getStatusCode(), 'Session must work just before the idle limit.');
    $check(['started_at' => $base, 'last_activity_at' => $base + 1799] === $session->get(WebSessionLifetime::KEY), 'Normal activity must retain the original absolute start.');
    $oldId = $session->getId();
    $oldCsrf = $csrf($http('/entrar'));
    $clock->timestamp = $base + 3599;
    $expired = $http('/cuenta');
    $check(302 === $expired->getStatusCode() && '/entrar' === $expired->headers->get('Location'), 'Exact idle boundary must stop private navigation before its controller.');
    $check(!$session->has('_security_main') && !$session->has(WebSessionLifetime::KEY) && $oldId !== $session->getId(), 'Expiry must remove authentication/timestamps and invalidate the identifier.');
    $cookies = array_filter($expired->headers->getCookies(), static fn ($cookie): bool => '/.well-known/mercure' === $cookie->getPath() && $cookie->getExpiresTime() < time());
    $check(1 === count($cookies), 'Expiry must clear the private subscription cookie.');
    $check(str_contains($http('/entrar')->getContent(), 'Tu sesión ha caducado.'), 'The new anonymous session must display a readable expiry notice.');
    $http('/entrar', 'POST', ['email' => $user->email(), 'password' => 'synthetic web session password', '_csrf_token' => $oldCsrf]);
    $check(!$session->has('_security_main'), 'The old CSRF token must not authorize login after invalidation.');

    $clock->timestamp = $base;
    $login();
    foreach ([900, 1799] as $offset) {
        $clock->timestamp = $base + $offset;
        $check(200 === $http('/trimestre/en-vivo/acceso')->getStatusCode(), 'Automatic authorization must work while session is valid.');
        $check($base === $session->get(WebSessionLifetime::KEY)['last_activity_at'], 'Automatic renewal must not count as activity.');
    }
    $clock->timestamp = $base + 1800;
    $expired = $http('/trimestre/en-vivo/acceso');
    $check(401 === $expired->getStatusCode() && 'session_expired' === json_decode($expired->getContent(), true, flags: JSON_THROW_ON_ERROR)['error']['code'], 'Automatic renewal must stop at the idle boundary with private JSON.');
    $check(str_contains($expired->headers->get('Cache-Control'), 'no-store') && !$session->has('_security_main'), 'Automatic expiry must be private and remove authentication.');

    $clock->timestamp = $base;
    $login();
    $fragment = '/clientes/'.$profileId.'/trimestre?year=2026&quarter=4';
    $clock->timestamp = $base + 1500;
    $check(200 === $http($fragment, headers: ['X-Quarter-Fragment' => '1'])->getStatusCode(), 'Financial fragments must remain available before expiry.');
    $check($base === $session->get(WebSessionLifetime::KEY)['last_activity_at'], 'Automatic financial fragments must not renew inactivity.');
    $clock->timestamp = $base + 1700;
    $check(200 === $http($fragment, headers: ['X-Quarter-Fragment' => '1', 'X-Session-Activity' => 'manual'])->getStatusCode(), 'Explicit manual refresh must remain usable.');
    $check(['started_at' => $base, 'last_activity_at' => $base + 1700] === $session->get(WebSessionLifetime::KEY), 'Manual refresh must renew only inactivity.');
    $clock->timestamp = $base + 1799;
    $http($fragment, headers: ['X-Quarter-Fragment' => '1']);
    $check($base + 1700 === $session->get(WebSessionLifetime::KEY)['last_activity_at'], 'Later automatic refresh must not repeat the manual activity.');
    $clock->timestamp = $base + 3500;
    $check(401 === $http($fragment, headers: ['X-Quarter-Fragment' => '1'])->getStatusCode(), 'Expired fragments must return no financial HTML.');

    $clock->timestamp = $base;
    $login();
    for ($offset = 1500; $offset < 28800; $offset += 1500) {
        $clock->timestamp = $base + $offset;
        $check(200 === $http('/cuenta')->getStatusCode(), 'Recent user activity must keep idle time valid before the absolute boundary.');
    }
    $clock->timestamp = $base + 28800;
    $check(302 === $http('/cuenta')->getStatusCode() && !$session->has('_security_main'), 'Absolute duration must win despite recent activity.');

    $login(false);
    $check(200 === $http('/cuenta')->getStatusCode(), 'A new login using the expired session replacement must establish a fresh cycle.');
    $session->remove(WebSessionLifetime::KEY);
    $check(302 === $http('/cuenta')->getStatusCode() && !$session->has('_security_main'), 'Legacy sessions without metadata must reauthenticate.');
    $login();
    $session->set(WebSessionLifetime::KEY, ['started_at' => $clock->timestamp + 1, 'last_activity_at' => $clock->timestamp + 1]);
    $check(302 === $http('/cuenta')->getStatusCode(), 'Future timestamps must not extend a session.');
    $login();
    $clock->timestamp += 1800;
    $public = $http('/recuperar');
    $check(200 === $public->getStatusCode() && str_contains($public->getContent(), 'He olvidado mi contraseña'), 'Public recovery must remain usable after old authentication expires.');
    $check(!$session->has('_security_main') && !$session->has(WebSessionLifetime::KEY), 'A public page must discard expired authentication instead of renewing it.');
    $check(1 === count(array_filter($public->headers->getCookies(), static fn ($cookie): bool => '/.well-known/mercure' === $cookie->getPath() && $cookie->getExpiresTime() < time())), 'Public expiry must also clear the subscription cookie.');
    $login();
    $apiLogin = $http('/api/v1/auth/login', 'POST', ['email' => $user->email(), 'password' => 'synthetic web session password']);
    $jwt = json_decode($apiLogin->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    $state = $session->get(WebSessionLifetime::KEY);
    $clock->timestamp += 1800;
    $check(200 === $http('/api/v1/me', token: $jwt)->getStatusCode(), 'A valid API JWT must remain independent from expired web metadata.');
    $check($state === $session->get(WebSessionLifetime::KEY), 'Stateless API access must not renew web timestamps.');
    $check(303 === $http('/ingresos', 'POST')->getStatusCode(), 'An expired POST must be intercepted before business handling.');
    $login();
    $loginCsrf = $csrf($http('/entrar'));
    $limits = $container->get(AuthenticationRateLimits::class);
    for ($i = 0; $i < 5; ++$i) {
        $limits->login('127.0.0.1', $user->email());
    }
    $clock->timestamp += 1800;
    $blocked = $http('/entrar', 'POST', ['email' => $user->email(), 'password' => 'synthetic web session password', '_csrf_token' => $loginCsrf]);
    $check(303 === $blocked->getStatusCode(), 'Expiry must run before rate-limit middleware can render authenticated navigation.');
    $check(!$session->has('_security_main'), 'An early rate-limit response must not preserve expired authentication.');
} finally {
    $db->rollBack();
    $em->clear();
    $check(hash_equals($before, hash('sha256', json_encode($db->fetchAllAssociative('SELECT id, auth_version FROM identity_user ORDER BY id'), JSON_THROW_ON_ERROR))), 'Existing identities must remain unchanged after rollback.');
    $kernel->shutdown();
}
echo 'Session expiration integration checks: '.$checks." passed.\nControlled clock; synthetic fixtures rolled back; no mail or AI.\n";
