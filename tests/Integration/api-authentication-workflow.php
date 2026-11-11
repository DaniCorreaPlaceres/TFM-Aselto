<?php

// Explicit local integration check. Requires local RSA keys; synthetic users roll back.
require dirname(__DIR__).'/bootstrap.php';

use App\Asesoria\Domain\Firm;
use App\Asesoria\Infrastructure\Persistence\DoctrineFirmRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class ApiAuthenticationCheckKernel extends Kernel
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
        return parent::getCacheDir().'/api-authentication-check';
    }
}

$kernel = new ApiAuthenticationCheckKernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void { if (!$condition) { throw new RuntimeException($message); } ++$checks; };
$uuid = static fn (): string => Uuid::v4()->toRfc4122();
$password = ' synthetic pass phrase ';
$db->beginTransaction();
try {
    $users = new DoctrineUserRepository($em);
    $firms = new DoctrineFirmRepository($em);
    $firmId = $uuid();
    $otherFirmId = $uuid();
    $firms->save(new Firm($firmId, 'Asesoría sintética API'));
    $firms->save(new Firm($otherFirmId, 'Otra asesoría sintética API'));
    $accounts = [];
    foreach (Role::cases() as $role) {
        $user = new User($uuid(), $uuid().'@example.test', password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]), $role, in_array($role, [Role::FirmAdmin, Role::Advisor], true) ? $firmId : null, true);
        $users->save($user);
        $accounts[$role->value] = $user;
    }
    $inactive = new User($uuid(), $uuid().'@example.test', password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]), Role::Client, null, false);
    $users->save($inactive);
    $http = static function (string $url, string $method = 'GET', ?string $body = null, ?string $token = null, string $contentType = 'application/json', ?User $webUser = null) use ($kernel, $em): Symfony\Component\HttpFoundation\Response {
        // Simulate fresh HTTP request state while retaining the fixture transaction.
        $em->clear();
        $kernel->getContainer()->get('security.token_storage')->setToken(null);
        $server = ['CONTENT_TYPE' => $contentType];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $request = Request::create('http://localhost'.$url, $method, server: $server, content: $body);
        if (null !== $webUser) {
            $session = new Session(new MockArraySessionStorage());
            $security = SecurityUser::fromDomain($webUser);
            $session->set('_security_main', serialize(new UsernamePasswordToken($security, 'main', $security->getRoles())));
            $request->setSession($session);
            $request->cookies->set($session->getName(), $session->getId());
        }
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    };
    $json = static fn (Symfony\Component\HttpFoundation\Response $response): array => json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    $login = static fn (string $email, string $secret): Symfony\Component\HttpFoundation\Response => $http('/api/v1/auth/login', 'POST', json_encode(['email' => $email, 'password' => $secret], JSON_THROW_ON_ERROR));
    $tokens = [];
    foreach ($accounts as $role => $user) {
        $response = $login(' '.mb_strtoupper($user->email()).' ', $password);
        $check(200 === $response->getStatusCode(), 'An active role must authenticate.');
        $data = $json($response);
        $check(['token', 'token_type', 'expires_in'] === array_keys($data) && 'Bearer' === $data['token_type'] && 3600 === $data['expires_in'], 'The token response must match the contract.');
        $check(str_contains($response->headers->get('Cache-Control'), 'no-store') && [] === $response->headers->getCookies(), 'Login must not create a session or cache credentials.');
        $tokens[$role] = $data['token'];
        $response = $http('/api/v1/me?id='.$inactive->id(), token: $data['token']);
        $identity = $json($response);
        $check(200 === $response->getStatusCode() && $user->id() === $identity['id'] && $role === $identity['role'] && $user->firmId() === $identity['firm_id'], 'Me must return only the current identity and current firm.');
        $check(['id', 'email', 'role', 'firm_id', 'activated'] === array_keys($identity) && true === $identity['activated'], 'Me must not expose credentials or fiscal data.');
        $check(str_contains($response->headers->get('Cache-Control'), 'no-store'), 'Me must not be cached.');
    }
    $client = $accounts['client'];
    $wrong = $login($client->email(), 'wrong');
    $unknown = $login($uuid().'@example.test', $password);
    $disabled = $login($inactive->email(), $password);
    $check(401 === $wrong->getStatusCode() && 401 === $unknown->getStatusCode() && 401 === $disabled->getStatusCode(), 'Invalid credentials and inactive accounts must fail.');
    $check($json($wrong) === $json($unknown) && $json($unknown) === $json($disabled) && 'invalid_credentials' === $json($wrong)['error']['code'], 'Authentication errors must not enumerate accounts.');
    $check(401 === $login($client->email(), trim($password))->getStatusCode(), 'Passwords must not be trimmed.');
    foreach (['', '{', '[]', 'null', '{"email":"a@example.test"}', '{"email":[],"password":"test"}', '{"email":"a@example.test","password":1}', '{"email":"a@example.test","password":"test","role":"superadmin"}'] as $body) {
        $response = $http('/api/v1/auth/login', 'POST', $body);
        $check(400 === $response->getStatusCode() && 'invalid_request' === $json($response)['error']['code'], 'Invalid JSON login shapes must return a JSON 400.');
    }
    $check(415 === $http('/api/v1/auth/login', 'POST', 'email=a@example.test', contentType: 'application/x-www-form-urlencoded')->getStatusCode(), 'Form login must not be accepted by the API.');
    $check(413 === $http('/api/v1/auth/login', 'POST', str_repeat('x', 16_385))->getStatusCode(), 'Oversized login bodies must be rejected.');
    $missing = $http('/api/v1/me');
    $check(401 === $missing->getStatusCode() && 'token_missing' === $json($missing)['error']['code'] && 'Bearer' === $missing->headers->get('WWW-Authenticate'), 'A missing token must return a Bearer challenge.');
    $check(401 === $http('/api/v1/me?token='.urlencode($tokens['client']))->getStatusCode(), 'A token in the query string must not authenticate.');
    $check(401 === $http('/api/v1/me', token: 'invalid')->getStatusCode(), 'Malformed tokens must fail.');
    $segments = explode('.', $tokens['client']);
    $payload = json_decode(base64_decode(strtr($segments[1], '-_', '+/')), true, flags: JSON_THROW_ON_ERROR);
    $encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $altered = $segments[0].'.'.$encode(json_encode([...$payload, 'roles' => ['ROLE_SUPERADMIN']], JSON_THROW_ON_ERROR)).'.'.$segments[2];
    $check(401 === $http('/api/v1/me', token: $altered)->getStatusCode(), 'Changing signed roles must invalidate the token.');
    $key = openssl_pkey_get_private(file_get_contents(dirname(__DIR__, 2).'/config/jwt/private.pem'), (string) ($_ENV['JWT_PASSPHRASE'] ?? $_SERVER['JWT_PASSPHRASE'] ?? ''));
    if (false === $key) {
        throw new RuntimeException('Generate the local JWT keypair before this check.');
    }
    $sign = static function (array $claims, OpenSSLAsymmetricKey $key) use ($encode): string {
        $unsigned = $encode('{"typ":"JWT","alg":"RS256"}').'.'.$encode(json_encode($claims, JSON_THROW_ON_ERROR));
        if (!openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Synthetic JWT signing failed.');
        }

        return $unsigned.'.'.$encode($signature);
    };
    $expired = $sign([...$payload, 'iat' => time() - 7200, 'exp' => time() - 3600], $key);
    $response = $http('/api/v1/me', token: $expired);
    $check(401 === $response->getStatusCode() && 'token_expired' === $json($response)['error']['code'], 'Expired signed tokens must fail with a stable error.');
    $otherKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $check(false !== $otherKey && 401 === $http('/api/v1/me', token: $sign($payload, $otherKey))->getStatusCode(), 'A token signed by another key must fail.');
    $signedOldRoles = $sign([...$payload, 'roles' => ['ROLE_SUPERADMIN']], $key);
    $check('client' === $json($http('/api/v1/me', token: $signedOldRoles))['role'], 'Database roles must override signed stale roles.');
    $admin = $accounts['firm_admin'];
    $db->executeStatement('UPDATE identity_user SET role = ?, firm_id = ? WHERE id = ?', ['advisor', $otherFirmId, $admin->id()]);
    $identity = $json($http('/api/v1/me', token: $tokens['firm_admin']));
    $check('advisor' === $identity['role'] && $otherFirmId === $identity['firm_id'], 'Current role and firm changes must be visible immediately.');
    $db->executeStatement('UPDATE identity_user SET activated = 0 WHERE id = ?', [$client->id()]);
    $check(401 === $http('/api/v1/me', token: $tokens['client'])->getStatusCode(), 'A deactivated account must lose API access.');
    $db->executeStatement('DELETE FROM identity_user WHERE id = ?', [$accounts['superadmin']->id()]);
    $check(401 === $http('/api/v1/me', token: $tokens['superadmin'])->getStatusCode(), 'A deleted account must lose API access.');
    $check(401 === $http('/api/v1/me', webUser: $accounts['advisor'])->getStatusCode(), 'A web session cookie must not authenticate the API.');
    $check(302 === $http('/cuenta', token: $tokens['advisor'])->getStatusCode(), 'An API token must not create a web session.');
    $check(200 === $http('/cuenta', webUser: $accounts['advisor'])->getStatusCode(), 'Existing web sessions must still work.');
    $check(200 === $http('/entrar')->getStatusCode(), 'The web login page must remain available.');
    $response = $http('/api/v1/unknown');
    $check(404 === $response->getStatusCode() && 'not_found' === $json($response)['error']['code'], 'Unknown API routes must return JSON 404.');
    foreach ([['/api/v1/me', 'POST'], ['/api/v1/auth/login', 'GET']] as [$url, $method]) {
        $response = $http($url, $method);
        $check(405 === $response->getStatusCode() && 'method_not_allowed' === $json($response)['error']['code'] && null !== $response->headers->get('Allow'), 'Wrong methods must return JSON 405 with Allow.');
    }
    echo 'API authentication integration checks: '.$checks." passed.\n";
} finally {
    $db->rollBack();
    $em->clear();
    $kernel->shutdown();
}
echo "Synthetic users rolled back; no credentials or tokens printed.\n";
