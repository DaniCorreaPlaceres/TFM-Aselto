<?php

// Explicit local audit. Creates only synthetic accounts inside a rolled-back transaction.
require dirname(__DIR__, 2).'/tests/bootstrap.php';

use App\Identidad\Application\ChoosePassword;
use App\Identidad\Application\IssueAccessToken;
use App\Identidad\Domain\AccessPurpose;
use App\Identidad\Domain\InvalidAccessToken;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Mail\CanonicalAccountUrls;
use App\Identidad\Infrastructure\Mail\SymfonyAccountNotifier;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Kernel;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class SecurityAuditKernel extends Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ([ChoosePassword::class, IssueAccessToken::class, CanonicalAccountUrls::class, 'security.token_storage'] as $id) {
                    $container->getDefinition($id)->setPublic(true);
                }
            }
        });
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/security-audit';
    }
}

$kernel = new SecurityAuditKernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();
$db = $em->getConnection();
$before = hash('sha256', json_encode($db->fetchAllAssociative('SELECT id, password_hash, auth_version FROM identity_user ORDER BY id'), JSON_THROW_ON_ERROR));
$results = ['recorded_at' => gmdate(DATE_ATOM), 'environment' => 'local-dev', 'external_mail_sent' => false, 'external_ai_called' => false];
$db->beginTransaction();
try {
    $users = new DoctrineUserRepository($em);
    $id = Uuid::v4()->toRfc4122();
    $email = $id.'@example.test';
    $initial = 'Synthetic audit initial password!';
    $updated = 'Synthetic audit updated password!';
    $user = new User($id, $email, password_hash($initial, PASSWORD_BCRYPT, ['cost' => 4]), Role::Client, null, true);
    $users->save($user);
    $http = static function (string $path, string $method = 'GET', ?array $data = null, ?string $token = null) use ($kernel, $container, $em) {
        $em->clear();
        $container->get('security.token_storage')->setToken(null);
        $server = ['CONTENT_TYPE' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $request = Request::create('http://localhost'.$path, $method, server: $server, content: null === $data ? null : json_encode($data, JSON_THROW_ON_ERROR));
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    };
    $login = $http('/api/v1/auth/login', 'POST', ['email' => $email, 'password' => $initial]);
    $token = json_decode($login->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'] ?? null;
    if (200 !== $login->getStatusCode() || !is_string($token)) {
        throw new RuntimeException('Synthetic authentication failed.');
    }
    $results['jwt_before_password_reset'] = $http('/api/v1/me', token: $token)->getStatusCode();
    $issuer = $container->get(IssueAccessToken::class);
    $choose = $container->get(ChoosePassword::class);
    $raw = $issuer->issue($users->find($id), AccessPurpose::Reset, '+2 hours');
    $choose->execute($raw, $updated, AccessPurpose::Reset);
    $afterReset = $http('/api/v1/me', token: $token);
    $results['jwt_after_password_reset'] = $afterReset->getStatusCode();
    if (401 === $afterReset->getStatusCode()) {
        $error = json_decode($afterReset->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $results['jwt_after_reset_error'] = $error['message'] ?? $error['error']['code'] ?? 'unauthorized';
    }
    $results['old_password_after_reset'] = $http('/api/v1/auth/login', 'POST', ['email' => $email, 'password' => $initial])->getStatusCode();
    $results['new_password_after_reset'] = $http('/api/v1/auth/login', 'POST', ['email' => $email, 'password' => $updated])->getStatusCode();
    $results['reset_token_reuse_rejected'] = false;
    try {
        $choose->assertUsable($raw, AccessPurpose::Reset);
    } catch (InvalidAccessToken) {
        $results['reset_token_reuse_rejected'] = true;
    }
    $expired = $issuer->issue($users->find($id), AccessPurpose::Reset, '-1 second');
    $results['expired_reset_token_rejected'] = false;
    try {
        $choose->assertUsable($expired, AccessPurpose::Reset);
    } catch (InvalidAccessToken) {
        $results['expired_reset_token_rejected'] = true;
    }
    $raw = $issuer->issue($users->find($id), AccessPurpose::Reset, '+2 hours');
    $choose->execute($raw, '12345678', AccessPurpose::Reset);
    $results['common_eight_character_password_accepted'] = true;
    $results['token_stored_only_as_hash'] = hash('sha256', $raw) === $db->fetchOne('SELECT token_hash FROM access_token WHERE user_id=? AND token_hash=?', [$id, hash('sha256', $raw)]);

    $mail = new class implements MailerInterface {
        public ?string $body = null;

        public function send(RawMessage $message, ?Envelope $envelope = null): void
        {
            $this->body = $message instanceof Email ? $message->getTextBody() : null;
        }
    };
    $router = $container->get('router');
    $originalContext = $router->getContext();
    try {
        $router->setContext(new RequestContext('', 'GET', 'audit-invalid.example.test', 'http', 9999));
        (new SymfonyAccountNotifier($mail, $container->get(CanonicalAccountUrls::class)))->sendPasswordReset('synthetic@example.test', str_repeat('a', 64));
        preg_match('#https?://[^\s]+#', $mail->body ?? '', $link);
        $results['reset_link_host_from_request_context'] = parse_url($link[0] ?? '', PHP_URL_HOST);
    } finally {
        $router->setContext($originalContext);
    }
    $session = new Session(new MockArraySessionStorage());
    $security = SecurityUser::fromDomain($users->find($id));
    $session->set('_security_main', serialize(new UsernamePasswordToken($security, 'main', $security->getRoles())));
    $container->get('security.token_storage')->setToken(null);
    $request = Request::create('http://localhost/salir', 'POST');
    $request->setSession($session);
    $request->cookies->set($session->getName(), $session->getId());
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    $results['logout_without_csrf_status'] = $response->getStatusCode();
    $results['logout_missing_csrf_rejected'] = in_array($response->getStatusCode(), [400, 403], true);
    $container->get('security.token_storage')->setToken(null);
    $payload = '"><img src=x onerror=alert(1)>';
    $request = Request::create('http://localhost/recuperar', 'POST', ['email' => $payload, '_csrf_token' => 'invalid']);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    $results['reflected_form_payload_escaped'] = str_contains($response->getContent(), '&lt;img') && !str_contains($response->getContent(), '<img src=x onerror=alert(1)>');
} finally {
    $db->rollBack();
    $em->clear();
    $results['identities_unchanged_after_rollback'] = hash_equals($before, hash('sha256', json_encode($db->fetchAllAssociative('SELECT id, password_hash, auth_version FROM identity_user ORDER BY id'), JSON_THROW_ON_ERROR)));
    $kernel->shutdown();
}
$production = new Kernel('prod', false);
try {
    $request = Request::create('http://localhost/audit-route-that-does-not-exist');
    $response = $production->handle($request);
    $production->terminate($request, $response);
    $results['production_mode_missing_route_status'] = $response->getStatusCode();
    $results['production_mode_error_without_stack_trace'] = 404 === $response->getStatusCode() && !str_contains($response->getContent(), 'Stack Trace') && !str_contains($response->getContent(), 'APP_SECRET');
} finally {
    $production->shutdown();
}
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
