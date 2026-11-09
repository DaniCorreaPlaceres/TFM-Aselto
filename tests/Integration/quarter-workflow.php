<?php

// Run explicitly in Docker against the migrated local database. Fixtures roll back.
require dirname(__DIR__).'/bootstrap.php';

use App\Asesoria\Domain\Firm;
use App\Asesoria\Infrastructure\Persistence\DoctrineFirmRepository;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Infrastructure\Persistence\DoctrineClientRepository;
use App\Documento\Application\AmendPersonalDocument;
use App\Documento\Application\ConfirmDocument;
use App\Documento\Application\DocumentForm;
use App\Documento\Application\DocumentWithholdingPolicy;
use App\Documento\Application\FirmDocumentAccess;
use App\Documento\Application\OpenClientDocument;
use App\Documento\Application\ReviewSubmittedDocument;
use App\Documento\Application\SaveDocumentDraft;
use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentChanged;
use App\Documento\Domain\DocumentStatus;
use App\Documento\Domain\ReviewAction;
use App\Documento\Infrastructure\Persistence\DoctrineDocumentRepository;
use App\Finanzas\Application\ChangeQuarter;
use App\Finanzas\Application\PreventClosedQuarterChanges;
use App\Finanzas\Application\QuarterAccess;
use App\Finanzas\Application\ShowQuarter;
use App\Finanzas\Domain\InvalidQuarter;
use App\Finanzas\Domain\QuarterChanged;
use App\Finanzas\Domain\QuarterNotFound;
use App\Finanzas\Domain\QuarterPeriod;
use App\Finanzas\Infrastructure\Persistence\DoctrineFinancialRecords;
use App\Finanzas\Infrastructure\Persistence\DoctrineQuarterRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Kernel;
use App\Shared\Domain\ClosedPeriod;
use App\Shared\Infrastructure\DoctrineClientTransaction;
use App\Shared\Infrastructure\SystemClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

$kernel = new Kernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$before = hash('sha256', json_encode($db->fetchAllAssociative('SELECT * FROM client_document ORDER BY id'), JSON_THROW_ON_ERROR));
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
};
$expect = static function (string $exception, callable $operation) use ($check): void {
    try {
        $operation();
    } catch (Throwable $error) {
        $check($error instanceof $exception, 'Unexpected exception: '.$error::class.' '.$error->getMessage());

        return;
    }
    throw new RuntimeException('Expected '.$exception);
};
$uuid = static fn (): string => Uuid::v4()->toRfc4122();
// Two independent database connections must serialize before entering a client mutation.
$lockOwner = $db->fetchOne('SELECT user_id FROM client_profile LIMIT 1');
if (false === $lockOwner) {
    throw new RuntimeException('Create a local demonstration client before running this integration check.');
}
$competing = Doctrine\DBAL\DriverManager::getConnection($db->getParams());
$competing->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
$competingEm = new Doctrine\ORM\EntityManager($competing, $em->getConfiguration());
$competingTransaction = new DoctrineClientTransaction($competingEm);
$entered = false;
$db->beginTransaction();
try {
    $db->fetchOne('SELECT id FROM client_profile WHERE user_id = ? FOR UPDATE', [$lockOwner]);
    $expect(Doctrine\DBAL\Exception\LockWaitTimeoutException::class, function () use ($competingTransaction, $lockOwner, &$entered): void {
        $competingTransaction->run($lockOwner, function () use (&$entered): void { $entered = true; });
    });
    $check(!$entered, 'A competing mutation must not enter before the lock is released.');
} finally {
    $db->rollBack();
}
$competingTransaction->run($lockOwner, function () use (&$entered): void { $entered = true; });
$check($entered, 'The competing mutation must proceed after the lock is released.');
$competing->close();
$db->beginTransaction();
try {
    $users = new DoctrineUserRepository($em);
    $clients = new DoctrineClientRepository($em);
    $documents = new DoctrineDocumentRepository($em);
    $firms = new DoctrineFirmRepository($em);
    $firm = new Firm($uuid(), 'Asesoría sintética de prueba');
    $otherFirm = new Firm($uuid(), 'Otra asesoría sintética');
    $firms->save($firm);
    $firms->save($otherFirm);
    $owner = new User($uuid(), $uuid().'@example.test', 'synthetic-hash', Role::Client, null, true);
    $linked = new User($uuid(), $uuid().'@example.test', 'synthetic-hash', Role::Client, $firm->id(), true);
    $admin = new User($uuid(), $uuid().'@example.test', 'synthetic-hash', Role::FirmAdmin, $firm->id(), true);
    $advisor = new User($uuid(), $uuid().'@example.test', 'synthetic-hash', Role::Advisor, $firm->id(), true);
    $other = new User($uuid(), $uuid().'@example.test', 'synthetic-hash', Role::Advisor, $otherFirm->id(), true);
    $root = new User($uuid(), $uuid().'@example.test', 'synthetic-hash', Role::Superadmin, null, true);
    foreach ([$owner, $linked, $admin, $advisor, $other, $root] as $user) {
        $users->save($user);
    }
    $personal = new Client($uuid(), $owner->id(), ClientKind::SelfEmployed, 'Cliente personal sintético', null);
    $assigned = new Client($uuid(), $linked->id(), ClientKind::SelfEmployed, 'Cliente asesoría sintético', $firm->id());
    $clients->save($personal);
    $clients->save($assigned);
    $clock = new SystemClock();
    $transaction = new DoctrineClientTransaction($em);
    $quarters = new DoctrineQuarterRepository($em);
    $source = new DoctrineFinancialRecords($em);
    $access = new QuarterAccess($users, $clients);
    $show = new ShowQuarter($access, $quarters, $source, $clock);
    $change = new ChangeQuarter($access, $quarters, $source, $transaction, $clock);
    $guard = new PreventClosedQuarterChanges($quarters);
    $open = new OpenClientDocument($documents);
    $policy = new DocumentWithholdingPolicy($clients);
    $save = new SaveDocumentDraft($open, $documents, $transaction, $policy, $guard);
    $confirm = new ConfirmDocument($open, $documents, $transaction, $policy, $clock, $guard);
    $amend = new AmendPersonalDocument($open, $clients, $documents, $transaction, $guard, $clock);
    $review = new ReviewSubmittedDocument(new FirmDocumentAccess($users, $clients, $documents), $documents, $policy, $clock, $transaction, $guard);
    $period = new QuarterPeriod(2026, 4);
    $make = static fn (User $user): Document => Document::receive($uuid(), $user->id(), $user->firmId(), 'synthetic.pdf', 'synthetic.pdf', 'application/pdf', $clock->now());
    $document = $make($owner)->confirm(DocumentForm::read('ingreso', '2026-11-01', 'Ejemplo sintético', '00000000T', '100', '21', withholdingRate: '15'), $clock->now());
    $documents->save($document);
    $undated = $make($owner);
    $documents->save($undated);
    $view = $show->execute($owner->id(), null, $period);
    $check(10000 === $view->calculation->summary->incomeCents(), 'Personal submitted records must count.');
    $check(1500 === $view->calculation->summary->withholdingCents(), 'Withholding must be separate.');
    $expect(InvalidQuarter::class, fn () => $change->execute($owner->id(), null, $period, null, true, 0, $view->calculation->fingerprint));
    $confirm->execute($owner->id(), $undated->id(), 'gasto', '2026-09-30', 'Ejemplo', '00000000T', '0', '0', expectedVersion: 1);
    $view = $show->execute($owner->id(), null, $period);
    $change->execute($owner->id(), null, $period, null, true, 0, $view->calculation->fingerprint);
    $closed = $quarters->find($owner->id(), null, $period);
    $check($closed->closed && 1 === $closed->version, 'First close must persist and version.');
    $expect(QuarterChanged::class, fn () => $change->execute($owner->id(), null, $period, null, true, 0, $view->calculation->fingerprint));
    $expect(ClosedPeriod::class, fn () => $amend->execute($owner->id(), $document->id(), 1));
    $late = $make($owner)->saveDraft(DocumentForm::read('gasto', '2026-11-02', 'Tardío', '00000000T', '10', '21'));
    $transaction->run($owner->id(), fn () => $documents->save($late));
    $expect(ClosedPeriod::class, fn () => $confirm->execute($owner->id(), $late->id(), 'gasto', '2026-11-02', 'Tardío', '00000000T', '10', '21'));
    $expect(ClosedPeriod::class, fn () => $save->execute($owner->id(), $late->id(), 'gasto', '2027-01-02', 'Tardío', '00000000T', '10', '21'));
    $destination = $make($owner)->saveDraft(DocumentForm::read('gasto', '2026-09-30', 'Destino', '00000000T', '0', '0'));
    $documents->save($destination);
    $expect(ClosedPeriod::class, fn () => $save->execute($owner->id(), $destination->id(), 'gasto', '2026-11-02', 'Destino', '00000000T', '0', '0'));
    $check(10000 === $show->execute($owner->id(), null, $period)->record->snapshot->incomeCents(), 'Late upload must not change snapshot.');
    $change->execute($owner->id(), null, $period, null, false, 1);
    $expect(QuarterChanged::class, fn () => $change->execute($owner->id(), null, $period, null, false, 1));
    $amend->execute($owner->id(), $document->id(), 1);
    $check(DocumentStatus::Draft === $documents->find($document->id())->status(), 'Amend must return to draft.');
    $expect(DocumentChanged::class, fn () => $confirm->execute($owner->id(), $document->id(), 'ingreso', '2026-11-01', 'Ejemplo', '00000000T', '200', '21', expectedVersion: 1));
    $confirm->execute($owner->id(), $document->id(), 'ingreso', '2026-11-01', 'Ejemplo', '00000000T', '200', '21', expectedVersion: 2);
    $confirm->execute($owner->id(), $late->id(), 'gasto', '2026-11-02', 'Ejemplo', '00000000T', '10', '21', expectedVersion: 1);
    $view = $show->execute($owner->id(), null, $period);
    $change->execute($owner->id(), null, $period, null, true, 2, $view->calculation->fingerprint);
    $em->clear();
    $closed = $quarters->find($owner->id(), null, $period);
    $check(3 === $closed->version && 3 === count($closed->history), 'Reclose must preserve all events and version.');
    $check(10000 === $closed->history[0]->snapshot->incomeCents() && 20000 === $closed->snapshot->incomeCents(), 'Old and new snapshots must be independent.');
    $firmDoc = $make($linked)->confirm(DocumentForm::read('gasto', '2026-11-03', 'Ejemplo', '00000000T', '100', '21', surcharge: '5.2'), $clock->now());
    $documents->save($firmDoc);
    $view = $show->execute($admin->id(), $assigned->id(), $period);
    $check(0 === $view->calculation->summary->expenseCents() && 1 === count($view->calculation->pending), 'Unvalidated advisory submission must remain pending.');
    $expect(InvalidQuarter::class, fn () => $change->execute($admin->id(), $assigned->id(), $period, null, true, 0, $view->calculation->fingerprint));
    $review->execute($advisor->id(), $firmDoc->id(), 1, ReviewAction::Validated, $firmDoc->details());
    $view = $show->execute($admin->id(), $assigned->id(), $period);
    $check(10000 === $view->calculation->summary->expenseCents() && 520 === $view->calculation->summary->surchargeCents(), 'Validated advisory expense and surcharge must count.');
    $change->execute($admin->id(), $assigned->id(), $period, null, true, 0, $view->calculation->fingerprint);
    $lateFirm = $make($linked)->confirm(DocumentForm::read('gasto', '2026-11-04', 'Ejemplo', '00000000T', '1', '21'), $clock->now());
    $transaction->run($linked->id(), fn () => $documents->save($lateFirm));
    $expect(ClosedPeriod::class, fn () => $review->execute($advisor->id(), $lateFirm->id(), 1, ReviewAction::Validated, $lateFirm->details()));
    $expect(QuarterNotFound::class, fn () => $change->execute($linked->id(), null, $period, null, false, 1));
    $expect(QuarterNotFound::class, fn () => $show->execute($other->id(), $assigned->id(), $period));
    $expect(QuarterNotFound::class, fn () => $show->execute($root->id(), $assigned->id(), $period));
    $change->execute($advisor->id(), $assigned->id(), $period, null, false, 1);

    // Real kernel requests with synthetic authenticated sessions, sharing the rolled-back connection.
    $sessions = [];
    $http = static function (User $actor, string $url, string $method = 'GET', array $data = []) use ($kernel, &$sessions): Symfony\Component\HttpFoundation\Response {
        $security = SecurityUser::fromDomain($actor);
        $session = $sessions[$actor->id()] ??= new Session(new MockArraySessionStorage());
        $session->set('_security_main', serialize(new UsernamePasswordToken($security, 'main', $security->getRoles())));
        $request = Request::create('http://localhost'.$url, $method, $data);
        $request->setSession($session);
        $request->cookies->set($session->getName(), $session->getId());
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    };
    $response = $http($owner, '/ingresos?year=2026&quarter=4');
    $check(200 === $response->getStatusCode() && str_contains($response->getContent(), '200,00') && str_contains($response->getContent(), 'Control personal'), 'Owner quarter HTML must render real frozen figures.');
    $profile = $http($admin, '/clientes/'.$assigned->id());
    $check(200 === $profile->getStatusCode() && str_contains($profile->getContent(), '/clientes/'.$assigned->id().'/trimestre'), 'Client fiscal profile must link to its quarter.');
    $response = $http($admin, '/clientes/'.$assigned->id().'/trimestre?year=2026&quarter=4');
    $check(200 === $response->getStatusCode() && str_contains($response->getContent(), 'Validado por la asesoría'), 'Advisory quarter must render.');
    $check(404 === $http($other, '/clientes/'.$assigned->id().'/trimestre')->getStatusCode(), 'Other firm HTTP access must fail.');
    $check(404 === $http($root, '/clientes/'.$assigned->id().'/trimestre')->getStatusCode(), 'Superadmin HTTP access must fail.');
    $check(404 === $http($owner, '/ingresos?quarter=5')->getStatusCode(), 'Invalid quarter HTTP access must fail.');
    $check(302 === $http($owner, '/ingresos?year=2026&quarter=4', 'POST', ['intent' => 'reopen', 'version' => 3, '_csrf_token' => 'invalid'])->getStatusCode(), 'Invalid CSRF must redirect.');
    $check($quarters->find($owner->id(), null, $period)->closed, 'Invalid CSRF must not reopen.');
    $fields = static function (string $html): array {
        preg_match_all('/name="(version|fingerprint|_csrf_token)" value="([^"]*)"/', $html, $matches, PREG_SET_ORDER);
        $fields = [];
        foreach ($matches as $match) {
            $fields[$match[1]] = html_entity_decode($match[2], ENT_QUOTES);
        }

        return $fields;
    };
    $response = $http($owner, '/ingresos?year=2026&quarter=4');
    $data = $fields($response->getContent());
    $check(302 === $http($owner, '/ingresos?year=2026&quarter=4', 'POST', [...$data, 'intent' => 'reopen'])->getStatusCode(), 'Valid reopen form must redirect.');
    $check(!$quarters->find($owner->id(), null, $period)->closed, 'Valid HTTP form must reopen.');
    $response = $http($owner, '/ingresos?year=2026&quarter=4');
    $data = $fields($response->getContent());
    $check(302 === $http($owner, '/ingresos?year=2026&quarter=4', 'POST', [...$data, 'intent' => 'close'])->getStatusCode(), 'Valid close form must redirect.');
    $check($quarters->find($owner->id(), null, $period)->closed, 'Valid HTTP form must close.');
    $check(5 === count($quarters->find($owner->id(), null, $period)->history), 'Web transitions must also preserve history.');
    $response = $http($owner, '/ingresos?year=2026&quarter=4');
    $http($owner, '/ingresos?year=2026&quarter=4', 'POST', [...$fields($response->getContent()), 'intent' => 'reopen']);
    $response = $http($owner, '/documentos/'.$document->id());
    $check(200 === $response->getStatusCode() && str_contains($response->getContent(), 'Corregir registro'), 'Personal confirmed record must offer a correction.');
    $check(302 === $http($owner, '/documentos/'.$document->id().'/corregir', 'POST', $fields($response->getContent()))->getStatusCode(), 'Correction form must redirect.');
    $check(DocumentStatus::Draft === $documents->find($document->id())->status(), 'Web correction must return to draft.');
    $response = $http($owner, '/documentos/'.$document->id());
    $data = [...$fields($response->getContent()), 'intent' => 'confirm', 'direction' => 'ingreso', 'issued_on' => '2026-11-01', 'party_name' => 'Ejemplo', 'tax_id' => '00000000T', 'base' => '250', 'tax_rate' => '21', 'surcharge' => '', 'withholding_rate' => ''];
    $check(302 === $http($owner, '/documentos/'.$document->id(), 'POST', $data)->getStatusCode(), 'Confirmation form must redirect.');
    $check(DocumentStatus::Submitted === $documents->find($document->id())->status() && 25000 === $documents->find($document->id())->baseCents(), 'Web confirmation must use the checked version and update the figures.');
    echo 'Quarter integration checks: '.$checks." passed.\n";
} finally {
    $db->rollBack();
    $em->clear();
    $after = hash('sha256', json_encode($db->fetchAllAssociative('SELECT * FROM client_document ORDER BY id'), JSON_THROW_ON_ERROR));
    $check($before === $after, 'Existing documents changed after rollback.');
    $kernel->shutdown();
}
echo "Synthetic fixtures rolled back; existing documents unchanged.\n";
