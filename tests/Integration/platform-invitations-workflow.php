<?php

// Explicit HTTP integration check on an isolated migrated database with synthetic fixtures.
require dirname(__DIR__).'/bootstrap.php';

use App\Identidad\Application\PlatformInvitations;
use App\Identidad\Domain\InvitationDeliveryFailed;
use App\Identidad\Domain\InvitationNotifier;
use App\Identidad\Domain\InvitationTarget;
use App\Identidad\Domain\InvitationUnavailable;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineInvitationRepository;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Kernel;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class InvitationRecordingNotifier implements InvitationNotifier
{
    /** @var list<array{email: string, target: InvitationTarget, token: string}> */
    public static array $messages = [];
    public static bool $fail = false;

    public function send(string $email, InvitationTarget $target, string $token): void
    {
        if (self::$fail) {
            throw new InvitationDeliveryFailed('Synthetic SMTP failure.');
        }
        self::$messages[] = compact('email', 'target', 'token');
    }
}

final class InvitationCheckKernel extends Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->setDefinition(InvitationRecordingNotifier::class, new Definition(InvitationRecordingNotifier::class));
                $container->setAlias(InvitationNotifier::class, InvitationRecordingNotifier::class);
                $container->getDefinition('security.token_storage')->setPublic(true);
                $container->getDefinition(PlatformInvitations::class)->setPublic(true);
            }
        });
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/platform-invitation-check';
    }
}

$kernel = new InvitationCheckKernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
};
$uuid = static fn (): string => Uuid::v4()->toRfc4122();
$prefix = $uuid();
$accounts = [];
$firmId = $uuid();
foreach (Role::cases() as $role) {
    $user = new User($uuid(), $prefix.'-'.$role->value.'@example.test', 'synthetic-hash', $role, in_array($role, [Role::FirmAdmin, Role::Advisor], true) ? $firmId : null, true);
    (new DoctrineUserRepository($em))->save($user);
    $accounts[$role->value] = $user;
}
$admin = $accounts[Role::Superadmin->value];
$session = new Session(new MockArraySessionStorage());
$http = static function (string $path, string $method = 'GET', array $data = [], ?User $actor = null) use ($kernel, $session): Response {
    $kernel->shutdown();
    $kernel->boot();
    $kernel->getContainer()->get('security.token_storage')->setToken(null);
    $session->remove('_security_main');
    if (null !== $actor) {
        $security = SecurityUser::fromDomain($actor);
        $session->set('_security_main', serialize(new UsernamePasswordToken($security, 'main', $security->getRoles())));
    }
    $request = Request::create('http://localhost'.$path, $method, $data);
    $request->setSession($session);
    $request->cookies->set($session->getName(), $session->getId());
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
};
$csrf = static function (Response $response): string {
    preg_match_all('/name="_csrf_token" value="([^"]+)"/', (string) $response->getContent(), $matches);

    return html_entity_decode(end($matches[1]) ?: '', ENT_QUOTES);
};
$form = ['name' => 'Registro ficticio', 'taxId' => '00000000T', 'phone' => '600000000', 'street' => 'Dirección ficticia 1', 'postalCode' => '28001', 'municipality' => 'Municipio de ejemplo', 'province' => 'Madrid', 'password' => 'synthetic-password', 'passwordConfirmation' => 'synthetic-password'];
$emails = [];
$createdFirms = [];

try {
    $check(302 === $http('/invitaciones')->getStatusCode(), 'Guests must be redirected to login.');
    foreach ([Role::FirmAdmin, Role::Advisor, Role::Client] as $role) {
        $actor = $accounts[$role->value];
        $check(403 === $http('/invitaciones', actor: $actor)->getStatusCode(), 'Only superadmin may list invitations.');
        $check(403 === $http('/invitaciones/nueva', actor: $actor)->getStatusCode(), 'Only superadmin may open send form.');
        $check(403 === $http('/invitaciones/nueva', 'POST', ['email' => 'blocked@example.test', 'target' => 'advisory'], $actor)->getStatusCode(), 'Only superadmin may send.');
        $check(403 === $http('/invitaciones/'.$uuid().'/reenviar', 'POST', [], $actor)->getStatusCode(), 'Only superadmin may resend.');
        try {
            $kernel->getContainer()->get(PlatformInvitations::class)->list($actor->id());
            throw new RuntimeException('Application access must also be checked.');
        } catch (InvitationUnavailable) {
            ++$checks;
        }
    }
    $check(422 === $http('/invitaciones/nueva', 'POST', ['email' => 'blocked@example.test', 'target' => 'advisory'], $admin)->getStatusCode(), 'Send must require CSRF.');
    foreach (InvitationTarget::cases() as $target) {
        $email = $prefix.'-'.$target->value.'@example.test';
        $emails[] = $email;
        $new = $http('/invitaciones/nueva', actor: $admin);
        $sent = $http('/invitaciones/nueva', 'POST', ['email' => strtoupper($email), 'target' => $target->value, '_csrf_token' => $csrf($new)], $admin);
        preg_match('~<p class="error"[^>]*>(.*?)</p>~s', (string) $sent->getContent(), $sendError);
        $check(302 === $sent->getStatusCode(), 'A superadmin must be able to invite each target: '.$sent->getStatusCode().' '.strip_tags($sendError[1] ?? ''));
        $message = InvitationRecordingNotifier::$messages[array_key_last(InvitationRecordingNotifier::$messages)];
        $check($email === $message['email'] && $target === $message['target'], 'Notification must target the normalized recipient and fixed account type.');
        $raw = $message['token'];
        $restoredInvitation = $kernel->getContainer()->get(PlatformInvitations::class)->inspect($raw);
        $check('UTC' === $restoredInvitation->expiresAt->getTimezone()->getName() && 86400 === $restoredInvitation->expiresAt->getTimestamp() - $restoredInvitation->sentAt->getTimestamp(), 'Hydrated invitation must retain exactly 24 hours in UTC.');
        $check(false === $db->fetchOne('SELECT id FROM identity_user WHERE email = ?', [$email]), 'Sending must not create an account.');
        $row = $db->fetchAssociative('SELECT * FROM platform_invitation WHERE email = ?', [$email]);
        $check(hash('sha256', $raw) === $row['token_hash'] && $raw !== $row['token_hash'], 'Only a hash must be stored.');
        $page = $http('/invitaciones', actor: $admin);
        $check(str_contains((string) $page->getContent(), 'Pendiente') && !str_contains((string) $page->getContent(), $raw) && !str_contains((string) $page->getContent(), $row['token_hash']), 'List must expose state without token material.');
        preg_match('~action="/invitaciones/'.$row['id'].'/reenviar".*?name="_csrf_token" value="([^"]+)"~s', (string) $page->getContent(), $initialResend);
        $resendCsrf = html_entity_decode($initialResend[1], ENT_QUOTES);
        if (InvitationTarget::Advisory === $target) {
            $new = $http('/invitaciones/nueva', actor: $admin);
            $check(422 === $http('/invitaciones/nueva', 'POST', ['email' => $email, 'target' => 'company', '_csrf_token' => $csrf($new)], $admin)->getStatusCode(), 'Pending invitations cannot be duplicated or change target.');
            $http('/invitaciones', actor: $admin);
            $manager = $kernel->getContainer()->get('doctrine')->getManager();
            $competitor = DriverManager::getConnection($db->getParams());
            $competitor->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
            (new DoctrineInvitationRepository($manager))->locked($row['id'], static function () use ($competitor, $row, $check): void {
                try {
                    $competitor->beginTransaction();
                    $competitor->fetchOne('SELECT id FROM platform_invitation WHERE id = ? FOR UPDATE', [$row['id']]);
                    throw new RuntimeException('Invitation lock did not serialize another writer.');
                } catch (LockWaitTimeoutException) {
                    $check(true, 'Another connection must wait for the invitation lock.');
                } finally {
                    $competitor->rollBack();
                }
            });
            $competitor->close();
            $check(403 === $http('/invitaciones/'.$row['id'].'/reenviar', 'POST', [], $admin)->getStatusCode(), 'Resend requires CSRF.');
            $db->executeStatement('UPDATE platform_invitation SET expires_at = ? WHERE id = ?', ['2020-01-01 00:00:00', $row['id']]);
            $check(410 === $http('/invitacion/'.$raw)->getStatusCode(), 'Expired invitations cannot be opened.');
            $page = $http('/invitaciones', actor: $admin);
            $check(str_contains((string) $page->getContent(), 'Caducada'), 'Expiry must be visible.');
            // The public form contains the masked CSRF token; extract this specific row.
            preg_match('~action="/invitaciones/'.$row['id'].'/reenviar".*?name="_csrf_token" value="([^"]+)"~s', (string) $page->getContent(), $matches);
            $check(302 === $http('/invitaciones/'.$row['id'].'/reenviar', 'POST', ['_csrf_token' => html_entity_decode($matches[1], ENT_QUOTES)], $admin)->getStatusCode(), 'Expired invitations may be resent.');
            $check(410 === $http('/invitacion/'.$raw)->getStatusCode(), 'Resend must invalidate the old link.');
            $raw = InvitationRecordingNotifier::$messages[array_key_last(InvitationRecordingNotifier::$messages)]['token'];
        }
        $url = '/invitacion/'.$raw;
        $acceptPage = $http($url);
        $check(200 === $acceptPage->getStatusCode() && str_contains((string) $acceptPage->headers->get('Cache-Control'), 'no-store') && 'no-referrer' === $acceptPage->headers->get('Referrer-Policy'), 'Acceptance must render privately without referrers.');
        $check(422 === $http($url, 'POST', $form)->getStatusCode(), 'Acceptance requires CSRF.');
        $acceptPage = $http($url);
        $check(422 === $http($url, 'POST', array_replace($form, ['password' => 'short', 'passwordConfirmation' => 'short', '_csrf_token' => $csrf($acceptPage)]))->getStatusCode(), 'Short passwords must be rejected.');
        $acceptPage = $http($url);
        $check(422 === $http($url, 'POST', array_replace($form, ['postalCode' => '35001', '_csrf_token' => $csrf($acceptPage)]))->getStatusCode(), 'Invalid fiscal contact must be rejected.');
        $acceptPage = $http($url);
        $check(422 === $http($url, 'POST', array_replace($form, ['passwordConfirmation' => 'different', '_csrf_token' => $csrf($acceptPage)]))->getStatusCode(), 'Passwords must match.');
        $acceptPage = $http($url);
        $check(422 === $http($url, 'POST', array_replace($form, ['name' => '', '_csrf_token' => $csrf($acceptPage)]))->getStatusCode(), 'Invalid profile must roll back account creation.');
        $check(false === $db->fetchOne('SELECT id FROM identity_user WHERE email = ?', [$email]), 'A failed profile must leave no account.');
        $acceptPage = $http($url);
        $accepted = $http($url, 'POST', $form + ['_csrf_token' => $csrf($acceptPage), 'email' => 'injected@example.test', 'target' => 'advisory', 'firmId' => $firmId, 'role' => 'superadmin']);
        $check(302 === $accepted->getStatusCode(), 'Valid acceptance must create the account.');
        $user = $db->fetchAssociative('SELECT * FROM identity_user WHERE email = ?', [$email]);
        $check(1 === (int) $user['activated'] && password_verify($form['password'], $user['password_hash']), 'The account must be activated with a hashed chosen password.');
        if (InvitationTarget::Advisory === $target) {
            $createdFirms[] = $user['firm_id'];
            $check('firm_admin' === $user['role'] && null !== $user['firm_id'], 'Advisory acceptance must create its admin.');
            $check('Registro ficticio' === $db->fetchOne('SELECT name FROM advisory_firm WHERE id = ?', [$user['firm_id']]), 'Advisory data must be recorded.');
        } else {
            $profile = $db->fetchAssociative('SELECT * FROM client_profile WHERE user_id = ?', [$user['id']]);
            $check('client' === $user['role'] && null === $user['firm_id'] && null === $profile['firm_id'], 'Independent clients must not join a firm.');
            $check($target->value === $profile['kind'], 'The invitation fixes the client kind.');
        }
        $check(410 === $http($url)->getStatusCode() && 410 === $http($url, 'POST', $form)->getStatusCode(), 'An accepted token must not be reused.');
        $check(null !== $db->fetchOne('SELECT accepted_at FROM platform_invitation WHERE email = ?', [$email]), 'Acceptance must be recorded.');
        $acceptedRow = $db->fetchAssociative('SELECT * FROM platform_invitation WHERE email = ?', [$email]);
        $check(302 === $http('/invitaciones/'.$row['id'].'/reenviar', 'POST', ['_csrf_token' => $resendCsrf], $admin)->getStatusCode(), 'Accepted resend must return safely to the list.');
        $check($acceptedRow === $db->fetchAssociative('SELECT * FROM platform_invitation WHERE email = ?', [$email]), 'Accepted invitations cannot be changed by resend.');
        $before = $user;
        $new = $http('/invitaciones/nueva', actor: $admin);
        $check(422 === $http('/invitaciones/nueva', 'POST', ['email' => $email, 'target' => 'advisory', '_csrf_token' => $csrf($new)], $admin)->getStatusCode(), 'Existing accounts cannot be invited again.');
        $check($before === $db->fetchAssociative('SELECT * FROM identity_user WHERE email = ?', [$email]), 'Duplicate invitations must preserve existing accounts exactly.');
    }
    $new = $http('/invitaciones/nueva', actor: $admin);
    $check(422 === $http('/invitaciones/nueva', 'POST', ['email' => 'invalid', 'target' => 'company', '_csrf_token' => $csrf($new)], $admin)->getStatusCode(), 'Invalid email must be rejected.');
    InvitationRecordingNotifier::$fail = true;
    $email = $prefix.'-smtp@example.test';
    $emails[] = $email;
    $new = $http('/invitaciones/nueva', actor: $admin);
    $check(302 === $http('/invitaciones/nueva', 'POST', ['email' => $email, 'target' => 'company', '_csrf_token' => $csrf($new)], $admin)->getStatusCode(), 'SMTP failure must be recoverable from the list.');
    $page = $http('/invitaciones', actor: $admin);
    $check(str_contains((string) $page->getContent(), 'no se pudo enviar el correo') && false !== $db->fetchOne('SELECT id FROM platform_invitation WHERE email = ?', [$email]), 'A failed email must leave a retryable invitation and an honest notice.');
    InvitationRecordingNotifier::$fail = false;
    $smtpRow = $db->fetchAssociative('SELECT * FROM platform_invitation WHERE email = ?', [$email]);
    preg_match('~action="/invitaciones/'.$smtpRow['id'].'/reenviar".*?name="_csrf_token" value="([^"]+)"~s', (string) $page->getContent(), $retry);
    $http('/invitaciones/'.$smtpRow['id'].'/reenviar', 'POST', ['_csrf_token' => html_entity_decode($retry[1], ENT_QUOTES)], $admin);
    $check($smtpRow['token_hash'] !== $db->fetchOne('SELECT token_hash FROM platform_invitation WHERE email = ?', [$email]), 'Retry after SMTP failure must renew the token.');
    $raw = InvitationRecordingNotifier::$messages[array_key_last(InvitationRecordingNotifier::$messages)]['token'];
    $external = new User($uuid(), $email, 'original-hash', Role::Client, null, true);
    (new DoctrineUserRepository($kernel->getContainer()->get('doctrine')->getManager()))->save($external);
    $check(410 === $http('/invitacion/'.$raw)->getStatusCode(), 'Registration through another path must invalidate acceptance.');
    $page = $http('/invitaciones', actor: $admin);
    $check(str_contains((string) $page->getContent(), 'Correo ya registrado'), 'An independently registered address must have an explicit blocked state.');
    $check('original-hash' === $db->fetchOne('SELECT password_hash FROM identity_user WHERE email = ?', [$email]), 'Blocking must preserve the independently registered account.');
} finally {
    $kernel->shutdown();
    foreach ($emails as $email) {
        $id = $db->fetchOne('SELECT id FROM identity_user WHERE email = ?', [$email]);
        if (false !== $id) {
            $db->executeStatement('DELETE FROM client_profile WHERE user_id = ?', [$id]);
            $db->executeStatement('DELETE FROM identity_user WHERE id = ?', [$id]);
        }
        $db->executeStatement('DELETE FROM platform_invitation WHERE email = ?', [$email]);
    }
    foreach ($createdFirms as $id) {
        $db->executeStatement('DELETE FROM advisory_firm WHERE id = ?', [$id]);
    }
    foreach ($accounts as $user) {
        $db->executeStatement('DELETE FROM identity_user WHERE id = ?', [$user->id()]);
    }
}

echo $checks." platform invitation checks passed.\n";
