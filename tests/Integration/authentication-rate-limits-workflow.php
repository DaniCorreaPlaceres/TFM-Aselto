<?php

// Local synthetic HTTP workflow. Counters and outgoing notifications stay in memory.
require dirname(__DIR__).'/bootstrap.php';

use App\Identidad\Domain\AccountNotifier;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Identidad\Infrastructure\Security\AuthenticationRateLimits;
use App\Kernel;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Uid\Uuid;

final class RateLimitRecordingNotifier implements AccountNotifier
{
    public int $messages = 0;

    public function sendActivation(string $email, string $token): void
    {
        ++$this->messages;
    }

    public function sendPasswordReset(string $email, string $token): void
    {
        ++$this->messages;
    }
}

final class AuthenticationRateLimitsCheckKernel extends Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->register('cache.authentication_limits', ArrayAdapter::class)->setPublic(true);
                $container->register(RateLimitRecordingNotifier::class, RateLimitRecordingNotifier::class)->setPublic(true);
                $container->setAlias(AccountNotifier::class, RateLimitRecordingNotifier::class);
                $container->getDefinition(AuthenticationRateLimits::class)->setPublic(true);
                $container->getDefinition('security.token_storage')->setPublic(true);
            }
        });
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/authentication-rate-limits-check';
    }
}

$kernel = new AuthenticationRateLimitsCheckKernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();
$db = $em->getConnection();
$before = hash('sha256', json_encode($db->fetchAllAssociative('SELECT id, auth_version FROM identity_user ORDER BY id'), JSON_THROW_ON_ERROR));
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
};
$session = new Session(new MockArraySessionStorage());
$http = static function (string $path, string $method = 'GET', array $data = [], string $ip = '192.0.2.1') use ($kernel, $container, $em, $session): Response {
    $em->clear();
    $container->get('security.token_storage')->setToken(null);
    $api = str_starts_with($path, '/api/');
    $request = Request::create('http://localhost'.$path, $method, $api ? [] : $data, server: ['REMOTE_ADDR' => $ip, 'CONTENT_TYPE' => $api ? 'application/json' : 'application/x-www-form-urlencoded'], content: $api ? json_encode($data, JSON_THROW_ON_ERROR) : null);
    $request->setSession($session);
    $request->cookies->set($session->getName(), $session->getId());
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
};
$csrf = static function (Response $response): string {
    preg_match('/name="_csrf_token" value="([^"]+)"/', $response->getContent(), $matches);

    return html_entity_decode($matches[1] ?? throw new RuntimeException('Missing synthetic CSRF token.'), ENT_QUOTES);
};
$db->beginTransaction();
try {
    $users = new DoctrineUserRepository($em);
    $email = Uuid::v4()->toRfc4122().'@example.test';
    $password = 'synthetic integration password';
    $active = new User(Uuid::v4()->toRfc4122(), $email, password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]), Role::Client, null, true);
    $inactive = new User(Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122().'@example.test', 'synthetic-hash', Role::Client, null);
    $users->save($active);
    $users->save($inactive);
    $loginCsrf = $csrf($http('/entrar'));
    for ($i = 0; $i < 3; ++$i) {
        $check(401 === $http('/api/v1/auth/login', 'POST', ['email' => $email, 'password' => 'wrong'])->getStatusCode(), 'Initial API errors must keep the generic 401.');
    }
    for ($i = 0; $i < 2; ++$i) {
        $check(302 === $http('/entrar', 'POST', ['email' => strtoupper($email), 'password' => 'wrong', '_csrf_token' => $loginCsrf])->getStatusCode(), 'Initial web errors must keep the normal login flow.');
    }
    $blocked = $http('/api/v1/auth/login', 'POST', ['email' => ' '.strtoupper($email).' ', 'password' => $password]);
    $check(429 === $blocked->getStatusCode(), 'Switching between web/API or email variants must not reset the budget.');
    $check('too_many_attempts' === json_decode($blocked->getContent(), true, flags: JSON_THROW_ON_ERROR)['error']['code'], 'API throttling must keep the JSON error contract.');
    $check((int) $blocked->headers->get('Retry-After') > 0 && str_contains($blocked->headers->get('Cache-Control'), 'no-store'), 'Throttled API responses need Retry-After and private no-store.');
    $blockedWeb = $http('/entrar', 'POST', ['email' => $email, 'password' => $password, '_csrf_token' => $loginCsrf]);
    $check(429 === $blockedWeb->getStatusCode() && str_contains($blockedWeb->getContent(), 'Has hecho demasiados intentos.'), 'Web login must display a Spanish retry message.');
    $check(429 === $http('/api/v1/auth/lo%67in', 'POST', ['email' => $email, 'password' => $password])->getStatusCode(), 'Percent-encoded API paths must not bypass the limiter.');
    $check(429 === $http('/entr%61r', 'POST', ['email' => $email, 'password' => $password, '_csrf_token' => $loginCsrf])->getStatusCode(), 'Percent-encoded web paths must not bypass the limiter.');
    $check(200 === $http('/api/v1/auth/login', 'POST', ['email' => $email, 'password' => $password], '192.0.2.2')->getStatusCode(), 'A different IP must not inherit a global account lock.');
    $limits = $container->get(AuthenticationRateLimits::class);
    for ($i = 0; $i < 60; ++$i) {
        $limits->login('192.0.2.30', $i.'@example.test');
    }
    $check(429 === $http('/api/v1/auth/login', 'POST', ['email' => 'new@example.test', 'password' => 'wrong'], '192.0.2.30')->getStatusCode(), 'Changing usernames must not bypass the global IP budget.');
    $check(200 === $http('/entrar', ip: '192.0.2.30')->getStatusCode(), 'GET forms must remain accessible even after an IP is throttled.');
    $recoveryCsrf = $csrf($http('/recuperar'));
    $notifier = $container->get(RateLimitRecordingNotifier::class);
    $unknown = Uuid::v4()->toRfc4122().'@example.test';
    $limitedBodies = [];
    foreach ([$email, $inactive->email(), $unknown] as $index => $recipient) {
        for ($i = 0; $i < 3; ++$i) {
            $response = $http('/recuperar', 'POST', ['email' => $recipient, '_csrf_token' => $recoveryCsrf], '192.0.2.'.(50 + $index));
            $check(302 === $response->getStatusCode() && '/entrar' === $response->headers->get('Location'), 'Existing, inactive and absent accounts must have the same accepted response.');
        }
        $snapshot = hash('sha256', json_encode($db->fetchAllAssociative('SELECT * FROM access_token ORDER BY id'), JSON_THROW_ON_ERROR));
        $messages = $notifier->messages;
        $response = $http('/recuperar', 'POST', ['email' => strtoupper($recipient), '_csrf_token' => $recoveryCsrf], '192.0.2.'.(70 + $index));
        $check(429 === $response->getStatusCode() && (int) $response->headers->get('Retry-After') > 0, 'Recovery email budget must apply across IPs and to all account states.');
        $check($messages === $notifier->messages && $snapshot === hash('sha256', json_encode($db->fetchAllAssociative('SELECT * FROM access_token ORDER BY id'), JSON_THROW_ON_ERROR)), 'A blocked request must not send mail or replace existing links.');
        preg_match('/<p class="error" role="alert">(.*?)<\/p>/', $response->getContent(), $match);
        $limitedBodies[] = $match[1] ?? '';
    }
    $check(6 === $notifier->messages, 'Only three active and three inactive account requests may emit notifications.');
    $check(1 === count(array_unique($limitedBodies)) && '' !== $limitedBodies[0], 'Throttled messages must not disclose whether an account exists.');
    for ($i = 0; $i < 10; ++$i) {
        $check(302 === $http('/recuperar', 'POST', ['email' => Uuid::v4()->toRfc4122().'@example.test', '_csrf_token' => $recoveryCsrf], '192.0.2.90')->getStatusCode(), 'Initial recovery IP budget must accept distinct unknown addresses.');
    }
    $check(429 === $http('/recuperar', 'POST', ['email' => 'another@example.test', '_csrf_token' => $recoveryCsrf], '192.0.2.90')->getStatusCode(), 'Changing email must not bypass the recovery IP budget.');
    for ($i = 0; $i < 4; ++$i) {
        $http('/recuperar', 'POST', ['email' => 'csrf@example.test', '_csrf_token' => 'invalid'], '192.0.2.95');
    }
    $check(302 === $http('/recuperar', 'POST', ['email' => 'csrf@example.test', '_csrf_token' => $recoveryCsrf], '192.0.2.95')->getStatusCode(), 'Failed CSRF must not consume the recovery email budget.');
    $check(1 === $users->find($active->id())->authVersion(), 'Throttling must not change account credentials.');
} finally {
    $db->rollBack();
    $em->clear();
    $check(hash_equals($before, hash('sha256', json_encode($db->fetchAllAssociative('SELECT id, auth_version FROM identity_user ORDER BY id'), JSON_THROW_ON_ERROR))), 'Existing identities must survive the workflow unchanged.');
    $kernel->shutdown();
}
echo 'Authentication rate limit integration checks: '.$checks." passed.\nSynthetic fixtures rolled back; notifications captured, counters isolated.\n";
