<?php

// Explicit MySQL check, only on a test database. Independent worker processes race for slot 20.
require dirname(__DIR__).'/bootstrap.php';

use App\Asesoria\Domain\Firm;
use App\Asesoria\Infrastructure\Persistence\DoctrineFirmRepository;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Infrastructure\Persistence\DoctrineClientRepository;
use App\Documento\Application\StoreClientDocument;
use App\Documento\Domain\DocumentExtractor;
use App\Documento\Domain\DocumentFileStore;
use App\Documento\Domain\ExtractionFailed;
use App\Documento\Domain\Proposal;
use App\Documento\Domain\Tax;
use App\Documento\Infrastructure\Persistence\DoctrineDocumentRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Kernel;
use App\Shared\Domain\Clock;
use App\Shared\Domain\DocumentQuotaExceeded;
use App\Shared\Domain\FiscalContact;
use App\Shared\Domain\LicensePlan;
use App\Shared\Domain\LicenseUnavailable;
use App\Shared\Domain\MonthlyQuota;
use App\Shared\Infrastructure\DoctrineClientTransaction;
use App\Shared\Infrastructure\DoctrineLicenses;
use App\Shared\Infrastructure\SymfonyIdGenerator;
use Symfony\Component\Uid\Uuid;

$kernel = new Kernel('test', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
if (!str_ends_with((string) $db->fetchOne('SELECT DATABASE()'), '_test')) {
    throw new RuntimeException('Use a separate database ending in _test.');
}
$licenses = new DoctrineLicenses($db);
$at = new DateTimeImmutable('2026-11-14 12:00:00 UTC');
$uuid = static fn (): string => Uuid::v4()->toRfc4122();
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void { if (!$condition) { throw new RuntimeException($message); } ++$checks; };

if ('--worker' === ($argv[1] ?? null)) {
    $files = new class implements DocumentFileStore {
        public function write(string $documentId, string $extension, string $contents): string
        {
            return 'synthetic/'.$documentId.'.'.$extension;
        }

        public function read(string $relativePath): string
        {
            return '';
        }
    };
    $extractor = new class implements DocumentExtractor {
        public function propose(string $mediaType, string $contents, Tax $tax = Tax::Vat, bool $allowsWithholding = false): Proposal
        {
            usleep(250000);
            throw new ExtractionFailed('Synthetic manual entry.');
        }
    };
    $clock = new class($at) implements Clock {
        public function __construct(private DateTimeImmutable $at)
        {
        }

        public function now(): DateTimeImmutable
        {
            return $this->at;
        }
    };
    $store = new StoreClientDocument(new DoctrineClientRepository($em), new DoctrineDocumentRepository($em), $files, $extractor, new SymfonyIdGenerator(), $clock, new DoctrineClientTransaction($em), $licenses);
    echo "READY\n";
    fflush(STDOUT);
    fgets(STDIN);
    try {
        $store->execute($argv[2], 'synthetic.pdf', 'application/pdf', "%PDF-1.4\n%%EOF");
        echo "CREATED\n";
    } catch (DocumentQuotaExceeded) {
        echo "BLOCKED\n";
    }
    exit(0);
}

$owner = $uuid();
$firm = $uuid();
$inactive = $uuid();
$workers = [];
try {
    $users = new DoctrineUserRepository($em);
    $clients = new DoctrineClientRepository($em);
    new DoctrineFirmRepository($em)->save(new Firm($firm, 'Asesoría sintética de licencias'));
    $users->save(new User($owner, $owner.'@example.test', 'synthetic-hash', Role::Client, null, true));
    $clients->save(new Client($uuid(), $owner, ClientKind::SelfEmployed, 'Cliente sintético de licencias', null, FiscalContact::required('00000000T', '600000000', 'Dirección ficticia', '28001', 'Municipio ficticio', 'Madrid')));
    $users->save(new User($inactive, $inactive.'@example.test', 'synthetic-hash', Role::Client, $firm, false));
    $clients->save(new Client($uuid(), $inactive, ClientKind::Company, 'Cliente sintético sin activar', $firm));
    $check(0 === $licenses->firm($firm)['clients'], 'Inactive accounts do not count.');
    for ($i = 0; $i < 19; ++$i) {
        $db->insert('document_usage', ['document_id' => $uuid(), 'owner_id' => $owner, 'month' => MonthlyQuota::month($at)]);
    }
    foreach ([1, 2] as $worker) {
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $owner], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Worker could not start.');
        }
        $workers[] = [$process, $pipes];
        $check("READY\n" === fgets($pipes[1]), 'Worker ready before releasing both uploads.');
    }
    foreach ($workers as [$process, $pipes]) {
        fwrite($pipes[0], "GO\n");
        fclose($pipes[0]);
    }
    $results = [];
    foreach ($workers as [$process, $pipes]) {
        $results[] = trim(stream_get_contents($pipes[1]));
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $check(0 === proc_close($process), 'Worker failed: '.$errors);
    }
    $workers = [];
    sort($results);
    $check(['BLOCKED', 'CREATED'] === $results, 'Exactly one concurrent upload may consume the last slot.');
    $check(20 === $licenses->quota($owner, $at)->used, 'Concurrent consumption stops at twenty.');
    $check(1 === (int) $db->fetchOne('SELECT COUNT(*) FROM client_document WHERE client_user_id=?', [$owner]), 'Only one original record persisted.');
    $db->executeStatement('DELETE FROM client_document WHERE client_user_id=?', [$owner]);
    $check(20 === $licenses->quota($owner, $at)->used, 'Deleting the document does not refund usage.');
    $check(0 === $licenses->quota($owner, $at->modify('+1 month'))->used, 'New month starts without resetting history.');
    $licenses->assign('client', $owner, LicensePlan::Professional, $owner, $at);
    $check(null === $licenses->quota($owner, $at)->limit() && 20 === $licenses->quota($owner, $at)->used, 'Professional immediately retains prior usage and removes monthly limit.');
    $db->transactional(function () use ($licenses, $owner, $uuid, $at): void { $licenses->consume($owner, $uuid(), $at); });
    $licenses->assign('client', $owner, LicensePlan::Free, $owner, $at);
    try {
        $db->transactional(function () use ($licenses, $owner, $uuid, $at): void { $licenses->consume($owner, $uuid(), $at); });
        throw new RuntimeException('Downgrade must block new uploads.');
    } catch (DocumentQuotaExceeded) {
        ++$checks;
    }
    $check(21 === $licenses->quota($owner, $at)->used, 'Blocked attempt consumes nothing.');
    $licenses->assign('client', $owner, LicensePlan::Professional, $owner, $at);
    $before = $licenses->quota($owner, $at)->used;
    try {
        $db->transactional(function () use ($licenses, $owner, $uuid, $at): void {
            $licenses->consume($owner, $uuid(), $at);
            throw new LogicException('Synthetic persistence failure.');
        });
    } catch (LogicException) {
    }
    $check($before === $licenses->quota($owner, $at)->used, 'Failure after reserving quota rolls back consumption.');
    $db->executeStatement('UPDATE client_profile SET firm_id=? WHERE user_id=?', [$firm, $owner]);
    $db->executeStatement('UPDATE identity_user SET firm_id=? WHERE id=?', [$firm, $owner]);
    $check(LicensePlan::Free === $licenses->quota($owner, $at)->plan, 'Linked client uses firm plan, not retained independent plan.');
    $licenses->assign('firm', $firm, LicensePlan::Advisory, $owner, $at);
    $check(LicensePlan::Advisory === $licenses->quota($owner, $at)->plan && 21 === $licenses->quota($owner, $at)->used, 'Advisory immediately covers linked clients without clearing usage.');
    $check(1 === $licenses->firm($firm)['clients'] && 299 === $licenses->firm($firm)['reference_cents'], 'Only activated linked clients count in the reference amount.');
    try {
        $licenses->assign('client', $owner, LicensePlan::Free, $owner, $at);
        throw new RuntimeException('Linked accounts cannot be assigned independent plans.');
    } catch (LicenseUnavailable) {
        ++$checks;
    }
    $db->executeStatement('UPDATE client_profile SET firm_id=NULL WHERE user_id=?', [$owner]);
    $db->executeStatement('UPDATE identity_user SET firm_id=NULL WHERE id=?', [$owner]);
    $check(LicensePlan::Professional === $licenses->quota($owner, $at)->plan, 'Unlinking restores the retained independent license.');
    $subjects = $licenses->subjects();
    $check(in_array($owner, array_column($subjects, 'id'), true) && in_array($firm, array_column($subjects, 'id'), true) && !in_array($inactive, array_column($subjects, 'id'), true), 'Administrative list includes firms and independent clients, excluding linked accounts.');
    echo "License integration checks: $checks passed, including two-process concurrent document creation.\n";
} finally {
    foreach ($workers as [$process, $pipes]) {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        } if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
    }
    foreach ([$owner, $inactive] as $id) {
        $db->executeStatement('DELETE FROM client_document WHERE client_user_id=?', [$id]);
        $db->executeStatement('DELETE FROM document_usage WHERE owner_id=?', [$id]);
        $db->executeStatement('DELETE FROM client_profile WHERE user_id=?', [$id]);
        $db->executeStatement('DELETE FROM identity_user WHERE id=?', [$id]);
        $db->executeStatement('DELETE FROM account_license WHERE subject_id=?', [$id]);
    }
    $db->executeStatement('DELETE FROM advisory_firm WHERE id=?', [$firm]);
    $db->executeStatement('DELETE FROM account_license WHERE subject_id=?', [$firm]);
    $kernel->shutdown();
}
