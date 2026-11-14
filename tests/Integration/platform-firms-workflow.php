<?php

// Explicit HTTP check against an isolated migrated MySQL. Only synthetic fixtures.
require dirname(__DIR__).'/bootstrap.php';

use App\Asesoria\Application\AdministerFirms;
use App\Asesoria\Domain\Firm;
use App\Asesoria\Infrastructure\Persistence\DoctrineFirmRepository;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Infrastructure\Persistence\DoctrineClientRepository;
use App\Identidad\Application\IssueAccessToken;
use App\Identidad\Domain\AccessPurpose;
use App\Identidad\Domain\EmailChangeDeliveryFailed;
use App\Identidad\Domain\EmailChangeNotifier;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Kernel;
use App\Shared\Domain\FiscalContact;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class FirmEmailRecordingNotifier implements EmailChangeNotifier
{
    /** @var list<array{email: string, token: string}> */
    public static array $requests = [];
    /** @var list<string> */
    public static array $notices = [];
    public static bool $failRequest = false;
    public static bool $failNotice = false;

    public function request(string $email, string $token): void
    {
        if (self::$failRequest) {
            throw new EmailChangeDeliveryFailed('Synthetic request delivery failure.');
        }
        self::$requests[] = compact('email', 'token');
    }

    public function confirmed(string $oldEmail): void
    {
        if (self::$failNotice) {
            throw new EmailChangeDeliveryFailed('Synthetic security notice failure.');
        }
        self::$notices[] = $oldEmail;
    }
}

final class FirmAdministrationCheckKernel extends Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->setDefinition(FirmEmailRecordingNotifier::class, new Definition(FirmEmailRecordingNotifier::class));
                $container->setAlias(EmailChangeNotifier::class, FirmEmailRecordingNotifier::class);
                foreach (['security.token_storage', IssueAccessToken::class, AdministerFirms::class] as $id) {
                    $container->getDefinition($id)->setPublic(true);
                }
            }
        });
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/platform-firm-check';
    }
}

$kernel = new FirmAdministrationCheckKernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$uuid = static fn (): string => Uuid::v4()->toRfc4122();
$prefix = $uuid();
$firmId = $uuid();
$otherFirmId = $uuid();
$profileId = $uuid();
$password = 'synthetic-password';
$contact = FiscalContact::required('B12345674', '600000000', 'Dirección ficticia 1', '28001', 'Municipio de ejemplo', 'Madrid');
$firms = new DoctrineFirmRepository($em);
$firms->save(new Firm($firmId, 'Asesoría ficticia', $contact));
$firms->save(new Firm($otherFirmId, 'Otra asesoría ficticia', $contact));
$accounts = [];
$users = new DoctrineUserRepository($em);
foreach (Role::cases() as $role) {
    $user = new User($uuid(), $prefix.'-'.$role->value.'@example.test', password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]), $role, Role::Superadmin === $role ? null : $firmId, true);
    $users->save($user);
    $accounts[$role->value] = $user;
}
$otherAdmin = new User($uuid(), $prefix.'-other@example.test', 'synthetic-hash', Role::FirmAdmin, $otherFirmId, true);
$users->save($otherAdmin);
$owner = $accounts['client'];
(new DoctrineClientRepository($em))->save(new Client($profileId, $owner->id(), ClientKind::SelfEmployed, 'Cliente ficticio', $firmId));
$root = $accounts['superadmin'];
$admin = $accounts['firm_admin'];
$newEmail = $prefix.'-new@example.test';
$newUserId = null;
$occupiedId = null;
$session = new Session(new MockArraySessionStorage());
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void { if (!$condition) { throw new RuntimeException($message); } ++$checks; };
$http = static function (string $path, string $method = 'GET', array $data = [], ?User $actor = null, ?string $jwt = null) use ($kernel, $session): Response {
    $kernel->shutdown();
    $kernel->boot();
    $kernel->getContainer()->get('security.token_storage')->setToken(null);
    $session->remove('_security_main');
    if (null !== $actor) {
        $security = SecurityUser::fromDomain($actor);
        $session->set('_security_main', serialize(new UsernamePasswordToken($security, 'main', $security->getRoles())));
    }
    $server = [];
    $content = null;
    if (str_starts_with($path, '/api/')) {
        $server['CONTENT_TYPE'] = 'application/json';
        $content = json_encode($data, JSON_THROW_ON_ERROR);
    }
    if (null !== $jwt) {
        $server['HTTP_AUTHORIZATION'] = 'Bearer '.$jwt;
    }
    $request = Request::create('http://localhost'.$path, $method, $data, server: $server, content: $content);
    $request->setSession($session);
    $request->cookies->set($session->getName(), $session->getId());
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
};
$hidden = static function (Response $response, string $field): string {
    preg_match_all('/name="'.preg_quote($field, '/').'" value="([^"]*)"/', (string) $response->getContent(), $matches);

    return html_entity_decode(end($matches[1]) ?: '', ENT_QUOTES);
};
$login = static function (string $email) use ($http, $password): string {
    $response = $http('/api/v1/auth/login', 'POST', ['email' => $email, 'password' => $password]);
    if (200 !== $response->getStatusCode()) {
        throw new RuntimeException('Synthetic API login failed: '.$response->getStatusCode());
    }

    return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
};
$url = '/asesorias/'.$firmId;
$fields = ['action' => 'details', 'name' => 'Asesoría editada', 'taxId' => 'B12345674', 'phone' => '600000000', 'street' => 'Dirección ficticia 2', 'postalCode' => '28001', 'municipality' => 'Municipio de ejemplo', 'province' => 'Madrid'];
$requestEmail = static function (string $email) use ($http, $url, $root, $hidden): Response {
    $page = $http($url, actor: $root);

    return $http($url, 'POST', ['action' => 'email', 'email' => $email, 'fingerprint' => $hidden($page, 'fingerprint'), '_csrf_token' => $hidden($page, '_csrf_token')], $root);
};

try {
    $check(302 === $http($url)->getStatusCode(), 'Guests cannot administer a firm.');
    foreach ([$admin, $accounts['advisor'], $owner, $otherAdmin] as $forbidden) {
        $check(403 === $http($url, actor: $forbidden)->getStatusCode(), 'Other roles cannot view administrative profiles.');
        $check(403 === $http($url, 'POST', $fields, $forbidden)->getStatusCode(), 'Other roles cannot edit administrative profiles.');
    }
    $check(404 === $http('/asesorias/'.$uuid(), actor: $root)->getStatusCode(), 'Missing firms must return 404.');
    $page = $http($url, actor: $root);
    $check(200 === $page->getStatusCode() && str_contains((string) $page->getContent(), $admin->email()), 'Superadmin sees administrative data and current email.');
    $check(str_contains((string) $page->headers->get('Cache-Control'), 'no-store'), 'Administrative data must not be cached.');
    $check(403 === $http($url, 'POST', $fields, $root)->getStatusCode(), 'Edits require CSRF.');
    $fingerprint = $hidden($page, 'fingerprint');
    $csrf = $hidden($page, '_csrf_token');
    $ownProfile = $http('/asesoria/datos', actor: $admin);
    $check(302 === $http($url, 'POST', $fields + ['fingerprint' => $fingerprint, '_csrf_token' => $csrf], $root)->getStatusCode(), 'Administrative fields may be updated.');
    $check('Asesoría editada' === $db->fetchOne('SELECT name FROM advisory_firm WHERE id = ?', [$firmId]), 'New firm name must persist.');
    $ownStale = $http('/asesoria/datos', 'POST', $fields + ['version' => $hidden($ownProfile, 'version'), '_csrf_token' => $hidden($ownProfile, '_csrf_token')], $admin);
    $check(str_contains((string) $ownStale->getContent(), 'Los datos han cambiado') && 'Dirección ficticia 2' === $db->fetchOne('SELECT street FROM advisory_firm WHERE id = ?', [$firmId]), 'A stale own-firm profile must not overwrite administrative edits.');
    $check(409 === $http($url, 'POST', array_replace($fields, ['name' => 'Stale overwrite', 'fingerprint' => $fingerprint, '_csrf_token' => $csrf]), $root)->getStatusCode(), 'Stale forms must not overwrite current data.');
    $check('Asesoría editada' === $db->fetchOne('SELECT name FROM advisory_firm WHERE id = ?', [$firmId]), 'Stale edit must preserve current data.');
    $page = $http($url, actor: $root);
    $check(422 === $http($url, 'POST', array_replace($fields, ['postalCode' => '35001', 'fingerprint' => $hidden($page, 'fingerprint'), '_csrf_token' => $hidden($page, '_csrf_token')]), $root)->getStatusCode(), 'Fiscal validation is required.');
    $check(422 === $requestEmail($otherAdmin->email())->getStatusCode(), 'Email already belonging to an account cannot be requested.');
    $oldJwt = $login($admin->email());
    $rootJwt = $login($root->email());
    $check(200 === $http('/api/v1/me', jwt: $oldJwt)->getStatusCode(), 'The initial JWT works.');
    $check(302 === $requestEmail($newEmail)->getStatusCode(), 'Request must send confirmation.');
    $request = FirmEmailRecordingNotifier::$requests[array_key_last(FirmEmailRecordingNotifier::$requests)];
    $firstToken = $request['token'];
    $check($newEmail === $request['email'] && $admin->email() === $db->fetchOne('SELECT email FROM identity_user WHERE id = ?', [$admin->id()]), 'Current email remains active until confirmation.');
    $stillActive = $http('/api/v1/me', jwt: $oldJwt);
    $check(200 === $stillActive->getStatusCode(), 'Request alone must not revoke current API access: '.$stillActive->getStatusCode().' '.substr(strip_tags((string) $stillActive->getContent()), 0, 350));
    $change = $db->fetchAssociative('SELECT * FROM admin_email_change WHERE user_id = ?', [$admin->id()]);
    $check(hash('sha256', $firstToken) === $change['token_hash'] && abs(86400 - ((int) $change['expires_at'] - time())) < 10, 'Only a hash is stored and expiry is 24 hours.');
    $occupiedId = $uuid();
    (new DoctrineUserRepository($kernel->getContainer()->get('doctrine')->getManager()))->save(new User($occupiedId, $newEmail, 'synthetic-hash', Role::Client, null, true));
    $occupiedPage = $http('/confirmar-correo-admin/'.$firstToken);
    $occupiedResult = $http('/confirmar-correo-admin/'.$firstToken, 'POST', ['_csrf_token' => $hidden($occupiedPage, '_csrf_token')]);
    $check(410 === $occupiedResult->getStatusCode() && $admin->email() === $db->fetchOne('SELECT email FROM identity_user WHERE id = ?', [$admin->id()]), 'An address registered after the request must block confirmation without changing the admin.');
    $db->executeStatement('DELETE FROM identity_user WHERE id = ?', [$occupiedId]);
    $check(302 === $requestEmail($newEmail)->getStatusCode(), 'Resend is allowed.');
    $check(410 === $http('/confirmar-correo-admin/'.$firstToken)->getStatusCode(), 'A replaced token is invalid.');
    $token = FirmEmailRecordingNotifier::$requests[array_key_last(FirmEmailRecordingNotifier::$requests)]['token'];
    $db->executeStatement('UPDATE admin_email_change SET expires_at = 1 WHERE user_id = ?', [$admin->id()]);
    $check(410 === $http('/confirmar-correo-admin/'.$token)->getStatusCode(), 'Expired links are rejected.');
    FirmEmailRecordingNotifier::$failRequest = true;
    $check(302 === $requestEmail($newEmail)->getStatusCode(), 'SMTP failure leaves a recoverable pending change.');
    $check($admin->email() === $db->fetchOne('SELECT email FROM identity_user WHERE id = ?', [$admin->id()]), 'SMTP failure cannot replace the email.');
    FirmEmailRecordingNotifier::$failRequest = false;
    $requestEmail($newEmail);
    $token = FirmEmailRecordingNotifier::$requests[array_key_last(FirmEmailRecordingNotifier::$requests)]['token'];
    $confirmUrl = '/confirmar-correo-admin/'.$token;
    $confirm = $http($confirmUrl);
    $check(200 === $confirm->getStatusCode() && 'no-referrer' === $confirm->headers->get('Referrer-Policy'), 'Confirmation is private and GET does not mutate.');
    $check(422 === $http($confirmUrl, 'POST')->getStatusCode(), 'Confirmation requires CSRF.');
    $check($admin->email() === $db->fetchOne('SELECT email FROM identity_user WHERE id = ?', [$admin->id()]), 'GET and invalid CSRF cannot change identity.');
    $http($url, actor: $root);
    $tokenService = $kernel->getContainer()->get(IssueAccessToken::class);
    $tokenService->issue($admin, AccessPurpose::Reset, '+2 hours');
    $tokenService->issue($admin, AccessPurpose::Activation, '+24 hours');
    $before = $db->fetchAssociative('SELECT * FROM identity_user WHERE id = ?', [$admin->id()]);
    FirmEmailRecordingNotifier::$failNotice = true;
    $confirm = $http($confirmUrl);
    $done = $http($confirmUrl, 'POST', ['_csrf_token' => $hidden($confirm, '_csrf_token')]);
    $check(200 === $done->getStatusCode() && str_contains((string) $done->getContent(), 'Correo confirmado'), 'Confirmation commits even if the old-address notice fails.');
    $after = $db->fetchAssociative('SELECT * FROM identity_user WHERE id = ?', [$admin->id()]);
    $check($newEmail === $after['email'] && 2 === (int) $after['auth_version'], 'Confirmed address replaces the old address and revokes its generation.');
    foreach (['id', 'role', 'firm_id', 'password_hash', 'activated'] as $field) {
        $check($before[$field] === $after[$field], 'Identity, permissions and password must be preserved.');
    }
    $check(0 === (int) $db->fetchOne('SELECT COUNT(*) FROM access_token WHERE user_id = ?', [$admin->id()]), 'Old activation and reset links must be invalidated.');
    $check(401 === $http('/api/v1/me', jwt: $oldJwt)->getStatusCode(), 'Old JWT must fail.');
    $check(302 === $http('/cuenta', actor: $admin)->getStatusCode(), 'Old session must fail.');
    $check(410 === $http($confirmUrl)->getStatusCode(), 'Confirmed link is single-use.');
    $newJwt = $login($newEmail);
    $check(200 === $http('/api/v1/me', jwt: $newJwt)->getStatusCode(), 'New email and unchanged password must work.');
    $page = $http($url, actor: $root);
    $check(str_contains((string) $page->getContent(), 'Reenviar aviso de seguridad'), 'Unsent security notice is visible.');
    $check(422 === $requestEmail($prefix.'-blocked@example.test')->getStatusCode(), 'Another change is blocked until the outstanding security notice is sent.');
    $page = $http($url, actor: $root);
    FirmEmailRecordingNotifier::$failNotice = false;
    $check(302 === $http($url, 'POST', ['action' => 'notice', '_csrf_token' => $hidden($page, '_csrf_token')], $root)->getStatusCode(), 'Superadmin can retry the outstanding old-address notice.');
    $check([$admin->email()] === FirmEmailRecordingNotifier::$notices && 1 === (int) $db->fetchOne('SELECT notice_sent FROM admin_email_change WHERE user_id = ?', [$admin->id()]), 'Notice must reach the old address and be recorded.');
    $http($url, actor: $root);
    $newUserId = $uuid();
    (new DoctrineUserRepository($kernel->getContainer()->get('doctrine')->getManager()))->save(new User($newUserId, $admin->email(), 'synthetic-hash', Role::Client, null, true));
    $check(401 === $http('/api/v1/me', jwt: $oldJwt)->getStatusCode(), 'Reassigning the old email must not resurrect the former JWT.');
    $check(302 === $http('/cuenta', actor: $admin)->getStatusCode(), 'Reassigning the old email must not resurrect the former session.');
    foreach (['/documentos', '/bandeja', '/ingresos', '/mensajes', '/clientes/'.$profileId.'/trimestre', '/documentos/'.$uuid().'/archivo', '/bandeja/'.$uuid().'/archivo', '/mensajes/'.$uuid().'/adjuntos/'.$uuid()] as $privatePath) {
        $check(in_array($http($privatePath, actor: $root)->getStatusCode(), [403, 404], true), 'Administrative access must not expose private web activity: '.$privatePath);
    }
    foreach (['/api/v1/documents', '/api/v1/advisory/documents', '/api/v1/quarters/2026/4', '/api/v1/advisory/clients/'.$profileId.'/quarters/2026/4', '/api/v1/conversations', '/api/v1/documents/'.$uuid().'/file', '/api/v1/conversations/'.$uuid().'/attachments/'.$uuid()] as $privatePath) {
        $check(in_array($http($privatePath, jwt: $rootJwt)->getStatusCode(), [403, 404], true), 'Administrative access must not expose private API activity: '.$privatePath);
    }
    $check($firmId === $db->fetchOne('SELECT firm_id FROM client_profile WHERE id = ?', [$profileId]), 'Client membership must be unchanged.');
    $requestEmail($prefix.'-cancel@example.test');
    $cancelToken = FirmEmailRecordingNotifier::$requests[array_key_last(FirmEmailRecordingNotifier::$requests)]['token'];
    $page = $http($url, actor: $root);
    $check(302 === $http($url, 'POST', ['action' => 'cancel', 'fingerprint' => $hidden($page, 'fingerprint'), '_csrf_token' => $hidden($page, '_csrf_token')], $root)->getStatusCode(), 'Superadmin can cancel a pending change.');
    $check(410 === $http('/confirmar-correo-admin/'.$cancelToken)->getStatusCode() && $newEmail === $db->fetchOne('SELECT email FROM identity_user WHERE id = ?', [$admin->id()]), 'Cancellation invalidates the token without changing current identity.');
} finally {
    $kernel->shutdown();
    $db->executeStatement('DELETE FROM admin_email_change WHERE user_id = ?', [$admin->id()]);
    $db->executeStatement('DELETE FROM access_token WHERE user_id = ?', [$admin->id()]);
    $db->executeStatement('DELETE FROM client_profile WHERE id = ?', [$profileId]);
    foreach ([...array_values($accounts), $otherAdmin] as $user) {
        $db->executeStatement('DELETE FROM identity_user WHERE id = ?', [$user->id()]);
    }
    if (null !== $newUserId) {
        $db->executeStatement('DELETE FROM identity_user WHERE id = ?', [$newUserId]);
    }
    if (null !== $occupiedId) {
        $db->executeStatement('DELETE FROM identity_user WHERE id = ?', [$occupiedId]);
    }
    $db->executeStatement('DELETE FROM advisory_firm WHERE id IN (?, ?)', [$firmId, $otherFirmId]);
}

echo $checks." platform firm checks passed.\n";
