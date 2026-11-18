<?php

// Local HTTP password-policy check. Synthetic identities/tokens roll back; no mail or AI.
require dirname(__DIR__).'/bootstrap.php';

use App\Identidad\Application\IssueAccessToken;
use App\Identidad\Domain\AccessPurpose;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Kernel;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Uid\Uuid;

final class PasswordPolicyCheckKernel extends Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->register('cache.authentication_limits', ArrayAdapter::class);
                $container->getDefinition(IssueAccessToken::class)->setPublic(true);
                $container->getDefinition('security.token_storage')->setPublic(true);
            }
        });
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/password-policy-check';
    }
}

$kernel = new PasswordPolicyCheckKernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();
$db = $em->getConnection();
$before = hash('sha256', json_encode($db->fetchAllAssociative('SELECT id, auth_version FROM identity_user ORDER BY id'), JSON_THROW_ON_ERROR));
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void { if (!$condition) { throw new RuntimeException($message); } ++$checks; };
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
$loginAttempt = 100;
$login = static function (string $email, string $password) use ($http, &$loginAttempt): Response {
    return $http('/api/v1/auth/login', 'POST', ['email' => $email, 'password' => $password], '192.0.2.'.$loginAttempt++);
};
$db->beginTransaction();
try {
    $users = new DoctrineUserRepository($em);
    $issuer = $container->get(IssueAccessToken::class);
    foreach ([AccessPurpose::Activation, AccessPurpose::Reset] as $purpose) {
        $user = new User(Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122().'@example.test', password_hash('old-pass', PASSWORD_BCRYPT, ['cost' => 4]), Role::Client, null, AccessPurpose::Reset === $purpose);
        $users->save($user);
        if (AccessPurpose::Reset === $purpose) {
            $check(200 === $login($user->email(), 'old-pass')->getStatusCode(), 'Existing short credentials must keep authenticating.');
        }
        $token = $issuer->issue($user, $purpose, '+2 hours');
        $path = (AccessPurpose::Activation === $purpose ? '/activar/' : '/recuperar/').$token;
        $snapshot = static fn (): string => hash('sha256', json_encode([$db->fetchAssociative('SELECT * FROM identity_user WHERE id=?', [$user->id()]), $db->fetchAllAssociative('SELECT * FROM access_token WHERE user_id=? ORDER BY id', [$user->id()])], JSON_THROW_ON_ERROR));
        $original = $snapshot();
        $page = $http($path);
        $check(200 === $page->getStatusCode() && str_contains($page->getContent(), '15 y 128') && str_contains($page->getContent(), 'minlength="15"'), 'Password form must explain the current policy.');
        foreach ([str_repeat('ñ', 14) => 'al menos 15', 'films+pic+galeries' => 'demasiado común', ' FILMS+PIC+GALERIES ' => 'demasiado común', str_repeat('ñ', 129) => '128 caracteres'] as $password => $message) {
            $response = $http($path, 'POST', ['password' => $password, '_csrf_token' => $csrf($http($path))]);
            $check(422 === $response->getStatusCode() && str_contains($response->getContent(), $message), 'Every password selection flow must reject the policy violation with an actionable error.');
            $check($original === $snapshot(), 'Rejection must preserve credentials, authentication version and access link.');
        }
        $password = AccessPurpose::Activation === $purpose ? 'una frase larga' : '  '.str_repeat('ñ', 60).' alfa  ';
        $acceptedCsrf = $csrf($http($path));
        $accepted = $http($path, 'POST', ['password' => $password, '_csrf_token' => $acceptedCsrf]);
        $check(302 === $accepted->getStatusCode(), 'A valid phrase must complete activation/reset.');
        $current = $users->find($user->id());
        $check($current->isActivated() && $user->authVersion() + 1 === $current->authVersion(), 'Selection must activate and revoke prior authentication normally.');
        $check(200 === $login($user->email(), $password)->getStatusCode(), 'The exact chosen phrase must authenticate with the real configured hasher.');
        $version = $current->authVersion();
        $reused = $http($path, 'POST', ['password' => 'another valid synthetic phrase', '_csrf_token' => $acceptedCsrf]);
        $check(422 === $reused->getStatusCode() && str_contains($reused->getContent(), 'enlace ya no vale'), 'A consumed link must not restore access.');
        $check($version === $users->find($user->id())->authVersion(), 'A failed repeat must not alter the authentication version.');
        if (AccessPurpose::Reset === $purpose) {
            $check(401 === $login($user->email(), 'old-pass')->getStatusCode(), 'The replaced legacy password must stop working.');
            $check(401 === $login($user->email(), trim($password))->getStatusCode(), 'The real login must preserve exterior spaces.');
            $check(401 === $login($user->email(), '  '.str_repeat('ñ', 60).' beta  ')->getStatusCode(), 'Passwords sharing the first 72 bytes must remain distinct.');
        }
    }
} finally {
    $db->rollBack();
    $em->clear();
    $check(hash_equals($before, hash('sha256', json_encode($db->fetchAllAssociative('SELECT id, auth_version FROM identity_user ORDER BY id'), JSON_THROW_ON_ERROR))), 'Existing identities must remain unchanged after rollback.');
    $kernel->shutdown();
}
echo 'Password policy integration checks: '.$checks." passed.\nSynthetic identities and tokens rolled back; no mail or AI.\n";
