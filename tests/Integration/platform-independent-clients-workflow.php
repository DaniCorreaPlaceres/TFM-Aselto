<?php

// Explicit HTTP check on isolated migrated MySQL; synthetic records and files only.
require dirname(__DIR__).'/bootstrap.php';

use App\Asesoria\Domain\Firm;
use App\Asesoria\Infrastructure\Persistence\DoctrineFirmRepository;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Infrastructure\Persistence\DoctrineClientRepository;
use App\Comunicacion\Domain\Attachment;
use App\Comunicacion\Domain\Conversation;
use App\Comunicacion\Domain\Message;
use App\Comunicacion\Infrastructure\Persistence\DoctrineConversationRepository;
use App\Documento\Application\DocumentForm;
use App\Documento\Domain\Document;
use App\Documento\Infrastructure\Persistence\DoctrineDocumentRepository;
use App\Documento\Infrastructure\Storage\LocalDocumentFileStore;
use App\Finanzas\Domain\Quarter;
use App\Finanzas\Domain\QuarterCalculation;
use App\Finanzas\Domain\QuarterPeriod;
use App\Finanzas\Infrastructure\Persistence\DoctrineQuarterRepository;
use App\Identidad\Domain\AccountNotifier;
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

final class IndependentRecordingNotifier implements AccountNotifier
{
    /** @var list<string> */
    public static array $emails = [];

    public function sendActivation(string $email, string $token): void
    {
        self::$emails[] = $email;
    }

    public function sendPasswordReset(string $email, string $token): void
    {
        self::$emails[] = $email;
    }
}

final class IndependentClientCheckKernel extends Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->setDefinition(IndependentRecordingNotifier::class, new Definition(IndependentRecordingNotifier::class));
                $container->setAlias(AccountNotifier::class, IndependentRecordingNotifier::class);
                $container->getDefinition('security.token_storage')->setPublic(true);
            }
        });
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/independent-client-check';
    }
}

$kernel = new IndependentClientCheckKernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$uuid = static fn (): string => Uuid::v4()->toRfc4122();
$prefix = $uuid();
$firmId = $uuid();
$documentId = $uuid();
$conversationId = $uuid();
$fileId = $uuid();
$password = 'synthetic-password';
$users = new DoctrineUserRepository($em);
$clients = new DoctrineClientRepository($em);
$accounts = [];
foreach (['root' => Role::Superadmin, 'admin' => Role::FirmAdmin, 'advisor' => Role::Advisor, 'self' => Role::Client, 'company' => Role::Client, 'linked' => Role::Client, 'mismatch' => Role::Client] as $key => $role) {
    $firm = in_array($key, ['admin', 'advisor', 'linked', 'mismatch'], true) ? $firmId : null;
    $user = new User($uuid(), $prefix.'-'.$key.'@example.test', password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]), $role, $firm, 'company' !== $key);
    $users->save($user);
    $accounts[$key] = $user;
}
$contact = FiscalContact::required('00000000T', '600000000', 'Dirección ficticia 1', '28001', 'Municipio de ejemplo', 'Madrid');
(new DoctrineFirmRepository($em))->save(new Firm($firmId, 'Asesoría ficticia', $contact));
$profiles = [];
foreach (['self', 'company', 'linked', 'mismatch'] as $key) {
    $profiles[$key] = new Client($uuid(), $accounts[$key]->id(), 'company' === $key ? ClientKind::Company : ClientKind::SelfEmployed, $prefix.'-'.$key, 'linked' === $key ? $firmId : null, $contact);
    $clients->save($profiles[$key]);
}
$root = $accounts['root'];
$owner = $accounts['self'];
$clientId = $profiles['self']->id();
$storage = new LocalDocumentFileStore(dirname(__DIR__, 2));
$relative = $storage->write($documentId, 'pdf', "%PDF-1.4\nSynthetic private content\n%%EOF");
$private = Document::receive($documentId, $owner->id(), null, 'synthetic.pdf', $relative, 'application/pdf', new DateTimeImmutable())->saveDraft(DocumentForm::read('ingreso', '2026-11-01', 'Tercero ficticio', '00000000T', '123', '21'));
(new DoctrineDocumentRepository($em))->save($private);
$period = new QuarterPeriod(2026, 3);
(new DoctrineQuarterRepository($em))->save(Quarter::firstClose($owner->id(), null, $period, (new QuarterCalculation($owner->id(), null, $period, []))->summary, $owner->id(), new DateTimeImmutable()));
(new DoctrineConversationRepository($em))->save(new Conversation($conversationId, $accounts['linked']->id(), $firmId, 'Conversación ficticia privada', false, null, [new Message($accounts['admin']->id(), new DateTimeImmutable(), 'Contenido privado ficticio', [new Attachment($fileId, 'synthetic.pdf', $relative, 'application/pdf')])]));
$snapshot = static fn (): string => hash('sha256', json_encode([$db->fetchAssociative('SELECT * FROM client_document WHERE id = ?', [$documentId]), $db->fetchAllAssociative('SELECT * FROM financial_quarter WHERE owner_id = ?', [$owner->id()]), $db->fetchAssociative('SELECT * FROM client_conversation WHERE id = ?', [$conversationId]), hash('sha256', $storage->read($relative))], JSON_THROW_ON_ERROR));
$before = $snapshot();
$session = new Session(new MockArraySessionStorage());
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void { if (!$condition) { throw new RuntimeException($message); } ++$checks; };
$http = static function (string $path, string $method = 'GET', array $data = [], ?User $actor = null, ?string $jwt = null) use ($kernel, $session): Response {
    $kernel->shutdown();
    $kernel->boot();
    $kernel->getContainer()->get('security.token_storage')->setToken(null);
    $session->remove('_security_main');
    if (null !== $actor) {
        $session->set('_security_main', serialize(new UsernamePasswordToken(SecurityUser::fromDomain($actor), 'main', SecurityUser::fromDomain($actor)->getRoles())));
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
$url = '/clientes-independientes/'.$clientId;
$fields = ['name' => 'Cliente independiente editado', 'taxId' => '00000000T', 'phone' => '600000000', 'street' => 'Dirección ficticia 2', 'postalCode' => '28001', 'municipality' => 'Municipio de ejemplo', 'province' => 'Madrid'];
$createdEmail = $prefix.'-created@example.test';

try {
    $check(302 === $http($url)->getStatusCode(), 'Guests cannot open administrative profiles.');
    foreach ([$owner, $accounts['admin'], $accounts['advisor']] as $actor) {
        $check(403 === $http($url, actor: $actor)->getStatusCode(), 'Other roles cannot open the profile.');
        $check(403 === $http($url, 'POST', $fields, $actor)->getStatusCode(), 'Other roles cannot submit edits.');
    }
    $page = $http('/clientes', actor: $root);
    $html = (string) $page->getContent();
    $check(200 === $page->getStatusCode() && str_contains($html, $profiles['self']->name()) && str_contains($html, $profiles['company']->name()), 'The list includes self-employed and company independent clients.');
    $check(!str_contains($html, $profiles['linked']->name()) && !str_contains($html, $profiles['mismatch']->name()), 'Linked and inconsistent accounts must not be listed.');
    $check(str_contains($html, 'Invitar a un autónomo') && str_contains($html, 'Invitar a una empresa') && str_contains($html, 'Dar de alta un cliente'), 'Invitations and full registration remain available.');
    $check(404 === $http('/clientes-independientes/'.$profiles['linked']->id(), actor: $root)->getStatusCode(), 'A linked client cannot be opened by the superadmin.');
    $check(404 === $http('/clientes-independientes/'.$profiles['mismatch']->id(), actor: $root)->getStatusCode(), 'A mismatched account cannot be opened.');
    $check(404 === $http('/clientes-independientes/'.$uuid(), actor: $root)->getStatusCode(), 'Missing clients return 404.');
    $check(200 === $http('/clientes-independientes/'.$profiles['company']->id(), actor: $root)->getStatusCode(), 'Pending activation does not prevent administrative consultation.');
    $legacy = $http('/clientes/'.$clientId, actor: $root);
    $check(302 === $legacy->getStatusCode() && $url === $legacy->headers->get('Location'), 'Legacy profile link leads to administrative detail.');
    $page = $http($url, actor: $root);
    $check(str_contains((string) $page->headers->get('Cache-Control'), 'no-store'), 'Profile responses must not be cached.');
    $check(!str_contains((string) $page->getContent(), 'Ver trimestre') && !str_contains((string) $page->getContent(), $relative), 'Administrative detail must contain no private activity links or paths.');
    $check(403 === $http($url, 'POST', $fields, $root)->getStatusCode(), 'Edits require CSRF.');
    $page = $http($url, actor: $root);
    $version = $hidden($page, 'version');
    $csrf = $hidden($page, '_csrf_token');
    $ownPage = $http('/perfil', actor: $owner);
    $accountBefore = $db->fetchAssociative('SELECT * FROM identity_user WHERE id = ?', [$owner->id()]);
    $check(302 === $http($url, 'POST', $fields + ['version' => $version, '_csrf_token' => $csrf, 'email' => 'injected@example.test', 'kind' => 'company', 'role' => 'superadmin', 'firmId' => $firmId, 'userId' => $root->id()], $root)->getStatusCode(), 'Valid administrative edits succeed.');
    $saved = $db->fetchAssociative('SELECT * FROM client_profile WHERE id = ?', [$clientId]);
    $check($fields['name'] === $saved['name'] && $fields['street'] === $saved['street'] && 2 === (int) $saved['version'], 'Name, contact and version must persist.');
    $check(null === $saved['firm_id'] && 'self_employed' === $saved['kind'] && $owner->id() === $saved['user_id'], 'Injected membership, kind and owner changes must be ignored.');
    $check($accountBefore === $db->fetchAssociative('SELECT * FROM identity_user WHERE id = ?', [$owner->id()]), 'The account must be preserved exactly.');
    $check($before === $snapshot(), 'Document data, file bytes, quarter snapshot and conversation must be unchanged.');
    $check(409 === $http($url, 'POST', array_replace($fields, ['name' => 'Stale overwrite', 'version' => $version, '_csrf_token' => $csrf]), $root)->getStatusCode(), 'Old administrative forms cannot overwrite edits.');
    $ownStale = $http('/perfil', 'POST', $fields + ['version' => $hidden($ownPage, 'version'), '_csrf_token' => $hidden($ownPage, '_csrf_token')], $owner);
    $check(422 === $ownStale->getStatusCode() && str_contains((string) $ownStale->getContent(), 'Los datos han cambiado'), 'An old owner profile cannot overwrite administrative contact edits.');
    $page = $http($url, actor: $root);
    $check(422 === $http($url, 'POST', array_replace($fields, ['postalCode' => '35001', 'version' => $hidden($page, 'version'), '_csrf_token' => $hidden($page, '_csrf_token')]), $root)->getStatusCode(), 'Invalid fiscal contact is rejected.');
    $page = $http($url, actor: $root);
    $check(422 === $http($url, 'POST', array_replace($fields, ['name' => '', 'version' => $hidden($page, 'version'), '_csrf_token' => $hidden($page, '_csrf_token')]), $root)->getStatusCode(), 'Empty names are rejected without partial updates.');
    $check($saved === $db->fetchAssociative('SELECT * FROM client_profile WHERE id = ?', [$clientId]), 'Invalid and stale forms must preserve the saved profile.');
    $jwtResponse = $http('/api/v1/auth/login', 'POST', ['email' => $root->email(), 'password' => $password]);
    $jwt = json_decode((string) $jwtResponse->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    foreach (['/documentos', '/bandeja', '/ingresos', '/mensajes', '/clientes/'.$clientId.'/trimestre', '/documentos/'.$documentId, '/documentos/'.$documentId.'/archivo', '/bandeja/'.$documentId.'/archivo', '/mensajes/'.$conversationId, '/mensajes/'.$conversationId.'/adjuntos/'.$fileId] as $path) {
        $check(in_array($http($path, actor: $root)->getStatusCode(), [403, 404], true), 'Private web access must be denied: '.$path);
    }
    foreach (['/api/v1/documents', '/api/v1/advisory/documents', '/api/v1/documents/'.$documentId, '/api/v1/documents/'.$documentId.'/file', '/api/v1/advisory/documents/'.$documentId.'/file', '/api/v1/quarters/2026/3', '/api/v1/advisory/clients/'.$clientId.'/quarters/2026/3', '/api/v1/conversations', '/api/v1/conversations/'.$conversationId, '/api/v1/conversations/'.$conversationId.'/attachments/'.$fileId] as $path) {
        $check(in_array($http($path, jwt: $jwt)->getStatusCode(), [403, 404], true), 'Private API access must be denied: '.$path);
    }
    $page = $http('/clientes', actor: $root);
    $invalidCreate = $http('/clientes', 'POST', array_replace($fields, ['name' => '', 'email' => $createdEmail, 'kind' => 'sociedad', '_csrf_token' => $hidden($page, '_csrf_token')]), $root);
    $check(422 === $invalidCreate->getStatusCode() && false === $db->fetchOne('SELECT id FROM identity_user WHERE email = ?', [$createdEmail]), 'An invalid direct registration must render safely without creating a partial account.');
    $page = $http('/clientes', actor: $root);
    $check(302 === $http('/clientes', 'POST', array_replace($fields, ['name' => 'Empresa ficticia nueva', 'email' => $createdEmail, 'kind' => 'sociedad', 'firmId' => $firmId, '_csrf_token' => $hidden($page, '_csrf_token')]), $root)->getStatusCode(), 'Direct independent registration remains available.');
    $created = $db->fetchAssociative('SELECT * FROM identity_user WHERE email = ?', [$createdEmail]);
    $createdProfile = $db->fetchAssociative('SELECT * FROM client_profile WHERE user_id = ?', [$created['id']]);
    $check(null === $created['firm_id'] && null === $createdProfile['firm_id'] && 'company' === $createdProfile['kind'] && 0 === (int) $created['activated'], 'Direct registration creates an independent company awaiting activation.');
    $check(in_array($createdEmail, IndependentRecordingNotifier::$emails, true), 'Direct registration sends an activation message through the port.');
    $page = $http($url, actor: $root);
    $db->executeStatement('UPDATE client_profile SET firm_id = ?, version = version + 1 WHERE id = ?', [$firmId, $clientId]);
    $db->executeStatement('UPDATE identity_user SET firm_id = ? WHERE id = ?', [$firmId, $owner->id()]);
    $check(404 === $http($url, 'POST', $fields + ['version' => $hidden($page, 'version'), '_csrf_token' => $hidden($page, '_csrf_token')], $root)->getStatusCode(), 'A client linked after form loading is no longer administratively editable.');
    $check('Cliente independiente editado' === $db->fetchOne('SELECT name FROM client_profile WHERE id = ?', [$clientId]), 'Linking must not allow a stale edit.');
    $check($before === $snapshot(), 'Private activity must remain unchanged after every administrative operation and denied read.');
} finally {
    $kernel->shutdown();
    $createdId = $db->fetchOne('SELECT id FROM identity_user WHERE email = ?', [$createdEmail]);
    if (false !== $createdId) {
        $db->executeStatement('DELETE FROM access_token WHERE user_id = ?', [$createdId]);
        $db->executeStatement('DELETE FROM client_profile WHERE user_id = ?', [$createdId]);
        $db->executeStatement('DELETE FROM identity_user WHERE id = ?', [$createdId]);
    }
    $db->executeStatement('DELETE FROM client_conversation WHERE id = ?', [$conversationId]);
    $db->executeStatement('DELETE FROM client_document WHERE id = ?', [$documentId]);
    $db->executeStatement('DELETE FROM financial_quarter WHERE owner_id = ?', [$owner->id()]);
    foreach ($profiles as $profile) {
        $db->executeStatement('DELETE FROM client_profile WHERE id = ?', [$profile->id()]);
    }
    foreach ($accounts as $account) {
        $db->executeStatement('DELETE FROM identity_user WHERE id = ?', [$account->id()]);
    }
    $db->executeStatement('DELETE FROM advisory_firm WHERE id = ?', [$firmId]);
    $absolute = realpath(dirname(__DIR__, 2).'/var/storage/'.$relative);
    $rootPath = realpath(dirname(__DIR__, 2).'/var/storage');
    if (false !== $absolute && false !== $rootPath && str_starts_with($absolute, $rootPath.DIRECTORY_SEPARATOR)) {
        unlink($absolute);
    }
}

echo $checks." independent client checks passed.\n";
