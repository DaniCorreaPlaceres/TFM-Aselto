<?php

// Real form login/logout with synthetic fixtures and in-memory session/counters.
require dirname(__DIR__).'/bootstrap.php';

use App\Asesoria\Domain\Firm;
use App\Asesoria\Infrastructure\Persistence\DoctrineFirmRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Identidad\Infrastructure\Security\WebSessionLifetime;
use App\Kernel;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Uid\Uuid;

final class LogoutWorkflowClock implements App\Shared\Domain\Clock
{
    private ?DateTimeImmutable $instant = null;

    public function now(): DateTimeImmutable
    {
        return $this->instant ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}

final class LogoutCsrfCheckKernel extends Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->register(LogoutWorkflowClock::class, LogoutWorkflowClock::class)->setPublic(true);
                $container->setAlias(App\Shared\Domain\Clock::class, LogoutWorkflowClock::class);
                $container->getDefinition(App\Shared\Infrastructure\Security\PrivateSecurityJournal::class)->setArgument('$directory', '/tmp/aselto-security-logout-check')->setPublic(true);
                $container->register('cache.authentication_limits', ArrayAdapter::class)->setPublic(true);
                $container->getDefinition('security.token_storage')->setPublic(true);
            }
        });
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/logout-csrf-check';
    }
}

$kernel = new LogoutCsrfCheckKernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$journal = $container->get(App\Shared\Infrastructure\Security\PrivateSecurityJournal::class);
assert($journal instanceof App\Shared\Infrastructure\Security\PrivateSecurityJournal);
foreach (glob($journal->directory().'/security-*.jsonl') ?: [] as $path) {
    unlink($path);
}
$em = $container->get('doctrine')->getManager();
$db = $em->getConnection();
$before = hash('sha256', json_encode($db->fetchAllAssociative('SELECT id, auth_version FROM identity_user ORDER BY id'), JSON_THROW_ON_ERROR));
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void { if (!$condition) { throw new RuntimeException($message); } ++$checks; };
$session = new Session(new MockArraySessionStorage());
$http = static function (string $path, string $method = 'GET', array $data = [], ?string $token = null) use ($kernel, $container, $em, &$session): Response {
    $em->clear();
    $container->get('security.token_storage')->setToken(null);
    $api = str_starts_with($path, '/api/');
    $request = Request::create('http://localhost'.$path, $method, $api ? [] : $data, server: ['CONTENT_TYPE' => $api ? 'application/json' : 'application/x-www-form-urlencoded'], content: $api ? json_encode($data, JSON_THROW_ON_ERROR) : null);
    if (null !== $token) {
        $request->headers->set('Authorization', 'Bearer '.$token);
    }
    $request->setSession($session);
    $request->cookies->set($session->getName(), $session->getId());
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
};
$csrf = static function (Response $response, string $action): string {
    preg_match('~action="'.preg_quote($action, '~').'"[\s\S]*?name="_csrf_token" value="([^"]*)"~', $response->getContent(), $match);

    return html_entity_decode($match[1] ?? throw new RuntimeException('Missing synthetic form token.'), ENT_QUOTES);
};
$cleared = static fn (Response $response): array => array_values(array_filter($response->headers->getCookies(), static fn ($cookie): bool => '/.well-known/mercure' === $cookie->getPath() && $cookie->getExpiresTime() < time()));
$db->beginTransaction();
try {
    $firmId = Uuid::v4()->toRfc4122();
    (new DoctrineFirmRepository($em))->save(new Firm($firmId, 'Asesoría sintética CSRF'));
    $user = new User(Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122().'@example.test', password_hash('synthetic logout password', PASSWORD_BCRYPT, ['cost' => 4]), Role::Advisor, $firmId, true);
    (new DoctrineUserRepository($em))->save($user);
    $login = static function (bool $fresh = true) use (&$session, $container, $http, $csrf, $user, $check): void {
        if ($fresh) {
            $session = new Session(new MockArraySessionStorage());
        }
        $container->get('cache.authentication_limits')->clear(); // Isolated test pool only.
        $form = $http('/entrar');
        $response = $http('/entrar', 'POST', ['email' => $user->email(), 'password' => 'synthetic logout password', '_csrf_token' => $csrf($form, '/entrar')]);
        $check(302 === $response->getStatusCode() && $session->has('_security_main') && $session->has(WebSessionLifetime::KEY), 'Real login must establish a current web session.');
    };
    $login();
    $original = $session;
    $valid = $csrf($http('/cuenta'), '/salir');
    $login();
    $foreign = $csrf($http('/cuenta'), '/salir');
    $session = $original;
    $wrongPurpose = $csrf($http('/entrar'), '/entrar');
    $subscription = $http('/trimestre/en-vivo/acceso');
    $check(200 === $subscription->getStatusCode() && 1 === count($subscription->headers->getCookies()), 'A valid session must obtain a private subscription before logout attempts.');
    $id = $session->getId();
    foreach ([[], ['_csrf_token' => 'invalid'], ['_csrf_token' => ['invalid']], ['_csrf_token' => $foreign], ['_csrf_token' => $wrongPurpose]] as $parameters) {
        $response = $http('/salir', 'POST', $parameters);
        $check(403 === $response->getStatusCode(), 'Missing, invalid, malformed, foreign and wrong-purpose tokens must be rejected.');
        $check($id === $session->getId() && $session->has('_security_main') && $session->has(WebSessionLifetime::KEY), 'Rejected logout must preserve the identifier and authentication.');
        $check([] === $cleared($response), 'Rejected logout must not revoke a valid subscription cookie.');
        $check(200 === $http('/cuenta')->getStatusCode(), 'A rejected logout must leave private access usable.');
    }
    foreach (['GET', 'HEAD', 'OPTIONS', 'PUT', 'PATCH', 'DELETE'] as $method) {
        $check(405 === $http('/salir?_csrf_token='.rawurlencode($valid), $method)->getStatusCode(), 'Only POST may reach logout, even with a valid token.');
    }
    $check(200 === $http('/cuenta')->getStatusCode() && $id === $session->getId(), 'GET logout must preserve authentication and session identity.');
    $encoded = $http('/sa%6cir', 'POST');
    $check(403 === $encoded->getStatusCode() && $session->has('_security_main'), 'Encoded route paths must not bypass CSRF.');
    $apiLogin = $http('/api/v1/auth/login', 'POST', ['email' => $user->email(), 'password' => 'synthetic logout password']);
    $jwt = json_decode($apiLogin->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    $check(200 === $http('/api/v1/me', token: $jwt)->getStatusCode(), 'A valid JWT must be tested before web logout.');
    $loggedOut = $http('/salir', 'POST', ['_csrf_token' => $valid]);
    $check(302 === $loggedOut->getStatusCode() && '/entrar' === parse_url($loggedOut->headers->get('Location'), PHP_URL_PATH) && 'localhost' === parse_url($loggedOut->headers->get('Location'), PHP_URL_HOST), 'The existing form token must close the session normally.');
    $check(!$session->has('_security_main') && !$session->has(WebSessionLifetime::KEY) && $id !== $session->getId(), 'Successful logout must invalidate authentication, timing and identifier.');
    $check(1 === count($cleared($loggedOut)), 'Successful logout must clear the private subscription cookie.');
    $check(302 === $http('/cuenta')->getStatusCode(), 'Private navigation must require login after logout.');
    $check(200 === $http('/api/v1/me', token: $jwt)->getStatusCode(), 'Web logout must not change the JWT contract.');
    $login(false);
    $newValid = $csrf($http('/cuenta'), '/salir');
    $check(403 === $http('/salir', 'POST', ['_csrf_token' => $valid])->getStatusCode(), 'A token from before logout must not authorize a later session.');
    $check(200 === $http('/cuenta')->getStatusCode(), 'A stale token must not close the new valid session.');
    $check(302 === $http('/salir', 'POST', ['_csrf_token' => $newValid])->getStatusCode(), 'The new session token must authorize logout.');
    $login();
    $now = $container->get(LogoutWorkflowClock::class)->now()->getTimestamp();
    $session->set(WebSessionLifetime::KEY, ['started_at' => $now - 1800, 'last_activity_at' => $now - 1800]);
    $expired = $http('/salir', 'POST');
    $check(303 === $expired->getStatusCode() && !$session->has('_security_main'), 'F05 must still expire an already timed-out session before logout CSRF.');
    $check(1 === count($cleared($expired)), 'Timeout must retain subscription cleanup independently of logout CSRF.');
    $events = array_values(iterator_to_array($journal->scan()));
    $logouts = array_values(array_filter($events, static fn ($entry): bool => App\Shared\Domain\SecurityEvent::Logout === $entry->event));
    $check(2 === count($logouts) && $user->id() === $logouts[0]->actor && $user->id() === $logouts[1]->actor, 'Only successful logouts must be recorded with the known actor.');
    $check(1 === count(array_filter($events, static fn ($entry): bool => App\Shared\Domain\SecurityEvent::SessionExpired === $entry->event)), 'Native timeout must emit one private security event.');
} finally {
    $db->rollBack();
    $em->clear();
    $check(hash_equals($before, hash('sha256', json_encode($db->fetchAllAssociative('SELECT id, auth_version FROM identity_user ORDER BY id'), JSON_THROW_ON_ERROR))), 'Existing identities must remain unchanged after rollback.');
    $kernel->shutdown();
    foreach (glob('/tmp/aselto-security-logout-check/security-*.jsonl') ?: [] as $path) {
        unlink($path);
    }
    rmdir('/tmp/aselto-security-logout-check');
}
echo 'Logout CSRF integration checks: '.$checks." passed.\nSynthetic fixtures rolled back; no mail, AI or token output.\n";
