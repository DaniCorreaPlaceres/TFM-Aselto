<?php

// Synthetic HTTP fixtures roll back. Notifications are captured, never delivered.
require dirname(__DIR__).'/bootstrap.php';

use App\Identidad\Application\ChoosePassword;
use App\Identidad\Application\IssueAccessToken;
use App\Identidad\Application\RequestPasswordReset;
use App\Identidad\Domain\AccessPurpose;
use App\Identidad\Domain\AccountNotifier;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Kernel;
use App\Shared\Domain\SecurityEvent;
use App\Shared\Infrastructure\Security\PrivateSecurityJournal;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Uid\Uuid;

final class SecurityRecordingNotifier implements AccountNotifier
{
    public function sendActivation(string $email, string $token): void
    {
    }

    public function sendPasswordReset(string $email, string $token): void
    {
    }
}
final class SecurityEmailNotifier implements App\Identidad\Domain\EmailChangeNotifier
{
    public static string $token = '';

    public function request(string $email, string $token): void
    {
        self::$token = $token;
    }

    public function confirmed(string $oldEmail): void
    {
    }
}
final class SecurityLoggingCheckKernel extends Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->register('cache.authentication_limits', Symfony\Component\Cache\Adapter\ArrayAdapter::class);
                $container->getDefinition(PrivateSecurityJournal::class)->setArgument('$directory', '/tmp/aselto-security-http-check')->setPublic(true);
                foreach (['security.token_storage', ChoosePassword::class, IssueAccessToken::class, RequestPasswordReset::class] as $id) {
                    $container->getDefinition($id)->setPublic(true);
                }
                $container->register(SecurityRecordingNotifier::class, SecurityRecordingNotifier::class);
                $container->setAlias(AccountNotifier::class, SecurityRecordingNotifier::class);
                $container->register(SecurityEmailNotifier::class, SecurityEmailNotifier::class);
                $container->setAlias(App\Identidad\Domain\EmailChangeNotifier::class, SecurityEmailNotifier::class);
                $container->getDefinition(App\Asesoria\Application\AdministerFirms::class)->setPublic(true);
            }
        });
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/security-logging-check';
    }
}
$kernel = new SecurityLoggingCheckKernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$journal = $container->get(PrivateSecurityJournal::class);
assert($journal instanceof PrivateSecurityJournal);
foreach (glob($journal->directory().'/security-*.jsonl') ?: [] as $path) {
    unlink($path);
}
$em = $container->get('doctrine')->getManager();
$db = $em->getConnection();
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void { if (!$condition) { throw new RuntimeException($message); } ++$checks; };
$session = new Session(new MockArraySessionStorage());
$http = static function (string $path, string $method = 'GET', array $data = []) use ($kernel, $container, $em, $session): Response {
    $em->clear();
    $container->get('security.token_storage')->setToken(null);
    $api = str_starts_with($path, '/api/');
    $request = Request::create('http://localhost'.$path, $method, $api ? [] : $data, server: ['CONTENT_TYPE' => $api ? 'application/json' : 'application/x-www-form-urlencoded', 'HTTP_X_REQUEST_ID' => 'SYNTHETIC_SECRET', 'REMOTE_ADDR' => '192.0.2.42'], content: $api ? json_encode($data, JSON_THROW_ON_ERROR) : null);
    $request->setSession($session);
    $request->cookies->set($session->getName(), $session->getId());
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
};
$csrf = static function (Response $response): string {
    preg_match('/name="_csrf_token" value="([^"]+)"/', $response->getContent(), $matches);

    return html_entity_decode($matches[1] ?? throw new RuntimeException('Missing synthetic CSRF.'), ENT_QUOTES);
};
$entries = static fn (): array => array_values(iterator_to_array($journal->scan()));
$db->beginTransaction();
try {
    $password = 'synthetic security testing phrase';
    $user = new User(Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122().'@example.test', password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]), Role::Client, null, true);
    (new DoctrineUserRepository($em))->save($user);
    $response = $http('/api/v1/auth/login', 'POST', ['email' => $user->email(), 'password' => 'SYNTHETIC_SECRET']);
    $check(401 === $response->getStatusCode(), 'Synthetic wrong login must fail.');
    $last = array_slice($entries(), -1)[0];
    $check(SecurityEvent::LoginFailed === $last->event && null !== $last->subjectHash, 'API failed login must have a pseudonymized event.');
    $check($last->correlation === $response->headers->get('X-Request-ID'), 'Header and entry must share server correlation.');
    $response = $http('/api/v1/auth/login', 'POST', ['email' => $user->email(), 'password' => $password]);
    $check(200 === $response->getStatusCode(), 'Synthetic API login must succeed.');
    $last = array_slice($entries(), -1)[0];
    $check(SecurityEvent::LoginSucceeded === $last->event && $user->id() === $last->actor, 'API success must identify only the actor UUID.');
    $token = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    $http('/api/v1/me?token=SYNTHETIC_SECRET');
    $check(SecurityEvent::AuthenticationRejected === array_slice($entries(), -1)[0]->event, 'Missing API credentials must be audited.');
    $csrfToken = $csrf($http('/entrar'));
    $http('/entrar', 'POST', ['email' => $user->email(), 'password' => 'SYNTHETIC_SECRET', '_csrf_token' => $csrfToken]);
    $check(SecurityEvent::LoginFailed === array_slice($entries(), -1)[0]->event, 'Web login failure must be audited.');
    $response = $http('/entrar', 'POST', ['email' => $user->email(), 'password' => $password, '_csrf_token' => $csrfToken]);
    $check(302 === $response->getStatusCode() && SecurityEvent::LoginSucceeded === array_slice($entries(), -1)[0]->event, 'Web login success must be audited.');
    $http('/asesorias');
    $check(SecurityEvent::AccessDenied === array_slice($entries(), -1)[0]->event, 'Cross-role access must be audited.');
    $container->get(RequestPasswordReset::class)->execute($user->email());
    $known = array_slice($entries(), -1)[0];
    $container->get(RequestPasswordReset::class)->execute('unknown-synthetic@example.test');
    $unknown = array_slice($entries(), -1)[0];
    $check(SecurityEvent::RecoveryRequested === $known->event && $known->event === $unknown->event && null === $known->target && null === $unknown->target, 'Recovery events must not identify account existence.');
    $raw = $container->get(IssueAccessToken::class)->issue($user, AccessPurpose::Reset, '+2 hours');
    $container->get(ChoosePassword::class)->execute($raw, 'a new synthetic security password', AccessPurpose::Reset);
    $check(SecurityEvent::PasswordChanged === array_slice($entries(), -1)[0]->event, 'Completed password change must be audited.');
    $before = count($entries());
    try {
        $container->get(ChoosePassword::class)->execute($raw, 'another synthetic password phrase', AccessPurpose::Reset);
    } catch (App\Identidad\Domain\InvalidAccessToken) {
    }
    $check(count($entries()) === $before, 'Rejected reset must never emit a successful change.');
    $contact = App\Shared\Domain\FiscalContact::required('B12345674', '600000000', 'Dirección ficticia', '28001', 'Municipio ficticio', 'Madrid');
    $firmId = Uuid::v4()->toRfc4122();
    (new App\Asesoria\Infrastructure\Persistence\DoctrineFirmRepository($em))->save(new App\Asesoria\Domain\Firm($firmId, 'Asesoría sintética', $contact));
    $root = new User(Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122().'@example.test', 'synthetic-hash', Role::Superadmin, null, true);
    $admin = new User(Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122().'@example.test', 'synthetic-hash', Role::FirmAdmin, $firmId, true);
    $users = new DoctrineUserRepository($em);
    $users->save($root);
    $users->save($admin);
    $management = $container->get(App\Asesoria\Application\AdministerFirms::class);
    $management->save($root->id(), $firmId, $management->open($root->id(), $firmId)->fingerprint(), 'Nombre sintético cambiado', $contact);
    $last = array_slice($entries(), -1)[0];
    $check(SecurityEvent::FirmChanged === $last->event && $root->id() === $last->actor && $firmId === $last->target, 'Administration must record successful actor and target.');
    $before = count($entries());
    try {
        $management->save($user->id(), $firmId, 'invalid', 'Rejected change', $contact);
    } catch (App\Asesoria\Domain\FirmProfileDenied) {
    }
    $check(count($entries()) === $before, 'Rejected administrative change must not emit success.');
    $management->requestEmail($root->id(), $firmId, $management->open($root->id(), $firmId)->fingerprint(), Uuid::v4()->toRfc4122().'@example.test');
    $check(SecurityEvent::EmailChangeRequested === array_slice($entries(), -1)[0]->event, 'Pending email change must be distinguished from confirmation.');
    $management->confirmEmail(SecurityEmailNotifier::$token);
    $check(SecurityEvent::EmailChanged === array_slice($entries(), -1)[0]->event, 'Confirmed email change must be audited.');
    for ($i = 0; $i < 12; ++$i) {
        $http('/api/v1/auth/login', 'POST', ['email' => 'limited@example.test', 'password' => 'SYNTHETIC_SECRET']);
    }
    $check(in_array(SecurityEvent::AuthenticationLimited, array_map(static fn ($entry) => $entry->event, $entries()), true), 'Authentication limit must be audited.');
    $json = json_encode(array_map(static fn ($entry) => $entry->data(), $entries()), JSON_THROW_ON_ERROR);
    foreach (['SYNTHETIC_SECRET', $password, $raw, $token, $user->email(), '192.0.2.42', $csrfToken, SecurityEmailNotifier::$token, $admin->email(), 'Nombre sintético cambiado', 'B12345674'] as $secret) {
        $check(!str_contains($json, $secret), 'Private channel must not contain raw credentials or personal input.');
    }
    $check($journal->healthy(), 'Private directory and files must be protected.');
    echo 'Security logging integration checks: '.$checks." passed. No external mail.\n";
} finally {
    $db->rollBack();
    $em->clear();
    $kernel->shutdown();
    foreach (glob('/tmp/aselto-security-http-check/security-*.jsonl') ?: [] as $path) {
        unlink($path);
    }
    rmdir('/tmp/aselto-security-http-check');
}
