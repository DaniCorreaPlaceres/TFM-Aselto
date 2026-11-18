<?php

// Explicit development check. Isolated identifiers and files; database fixtures roll back.
require dirname(__DIR__).'/bootstrap.php';

use App\Asesoria\Domain\Firm;
use App\Asesoria\Infrastructure\Persistence\DoctrineFirmRepository;
use App\Cliente\Infrastructure\Persistence\DoctrineClientRepository;
use App\Comunicacion\Infrastructure\Persistence\DoctrineConversationRepository;
use App\Documento\Domain\Tax;
use App\Documento\Infrastructure\Persistence\DoctrineDocumentRepository;
use App\Finanzas\Application\QuarterAccess;
use App\Finanzas\Application\ShowQuarter;
use App\Finanzas\Domain\QuarterNotFound;
use App\Finanzas\Domain\QuarterPeriod;
use App\Finanzas\Infrastructure\Persistence\DoctrineFinancialRecords;
use App\Finanzas\Infrastructure\Persistence\DoctrineQuarterRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Kernel;
use App\Shared\Infrastructure\Demo\DemoDataset;
use App\Shared\Infrastructure\Demo\SeedDemoData;
use App\Shared\Infrastructure\SystemClock;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Uid\Uuid;

$kernel = new Kernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$namespace = 'check-'.bin2hex(random_bytes(6));
$dataset = new DemoDataset($namespace);
$root = dirname(__DIR__, 2).'/var/'.$namespace;
mkdir($root, 0775, true);
$users = new DoctrineUserRepository($em);
$firms = new DoctrineFirmRepository($em);
$clients = new DoctrineClientRepository($em);
$documents = new DoctrineDocumentRepository($em);
$quarters = new DoctrineQuarterRepository($em);
$records = new DoctrineFinancialRecords($em);
$conversations = new DoctrineConversationRepository($em);
$hasher = new class implements UserPasswordHasherInterface {
    public function hashPassword(PasswordAuthenticatedUserInterface $user, string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 4]);
    }

    public function isPasswordValid(PasswordAuthenticatedUserInterface $user, string $plainPassword): bool
    {
        return password_verify($plainPassword, (string) $user->getPassword());
    }

    public function needsRehash(PasswordAuthenticatedUserInterface $user): bool
    {
        return false;
    }
};
$seed = static fn (string $environment = 'dev'): SeedDemoData => new SeedDemoData($em, $users, $firms, $clients, $documents, $quarters, $records, $conversations, $hasher, $dataset, $root, $environment);
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void { if (!$condition) { throw new RuntimeException($message); } ++$checks; };
$expect = static function (callable $operation) use ($check): void {
    try {
        $operation();
    } catch (RuntimeException) {
        $check(true, 'Expected safe rejection.');

        return;
    }
    throw new RuntimeException('Expected safe rejection.');
};
$snapshot = static function () use ($db): string {
    $state = [];
    foreach (['identity_user' => 'id', 'advisory_firm' => 'id', 'client_profile' => 'id', 'client_document' => 'id', 'financial_quarter' => 'owner_id, scope, year, number', 'client_conversation' => 'id'] as $table => $order) {
        $state[$table] = $db->fetchAllAssociative('SELECT * FROM '.$table.' ORDER BY '.$order);
    }

    return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
};
$before = $snapshot();
$db->beginTransaction();
try {
    $expect(static fn () => $seed('prod')->execute());
    $check($before === $snapshot(), 'Production guard does not touch data.');
    $db->createSavepoint('demo_collision');
    $users->save(new User(Uuid::v4()->toRfc4122(), $dataset->email('superadmin'), 'synthetic', Role::Superadmin, null, true));
    $expect(static fn () => $seed()->execute());
    $db->rollbackSavepoint('demo_collision');
    $em->clear();
    $check($before === $snapshot(), 'Foreign email collision preserves existing data.');
    $db->createSavepoint('demo_partial');
    $firms->save(new Firm($dataset->id('firm/sol'), 'Fixture de colisión'));
    $expect(static fn () => $seed()->execute());
    $db->rollbackSavepoint('demo_partial');
    $em->clear();
    $check($before === $snapshot(), 'Partial dataset rejected without repair.');
    mkdir($root.'/var/storage/documents', 0775, true);
    $collision = $root.'/var/storage/documents/'.$dataset->id('document/independiente/undated').'.pdf';
    file_put_contents($collision, 'existing synthetic file');
    $expect(static fn () => $seed()->execute());
    $check('existing synthetic file' === file_get_contents($collision), 'Existing file not overwritten.');
    $check(1 === count(glob($root.'/var/storage/documents/*') ?: []) && [] === (glob($root.'/var/storage/messages/*') ?: []), 'Files created before failure removed.');
    $check($before === $snapshot(), 'Failed load rolls back users and fiscal data.');
    unlink($collision);
    $check($seed()->execute(), 'First load creates demo dataset.');
    $check(33 === count(glob($root.'/var/storage/documents/*') ?: []) && 4 === count(glob($root.'/var/storage/messages/*') ?: []), 'Thirty-three original PDFs and four message attachments.');
    $show = new ShowQuarter(new QuarterAccess($users, $clients), $quarters, $records, new SystemClock());
    foreach (DemoDataset::clients() as $key => $client) {
        $owner = $dataset->id('user/'.$key);
        $profile = $clients->findByUserId($owner);
        $check(null !== $profile && $profile->contact()->isComplete() && Tax::forProvince($profile->contact()->province()) === $client['tax'], 'Complete fictitious profile and territory.');
        $q3 = $show->execute($owner, null, new QuarterPeriod(2026, 3));
        $check(true === $q3->record?->closed && 100000 === $q3->record->snapshot->incomeCents() && 20000 === $q3->record->snapshot->expenseCents(), 'Frozen Q3 totals.');
        $q4 = $show->execute($owner, null, new QuarterPeriod(2026, 4));
        $check(null === $q4->record && count($q4->calculation->pending) >= 2, 'Q4 open with blockers.');
        $check(100000 === $q4->calculation->summary->incomeCents(), 'Current included income.');
        if (null !== $client['firm']) {
            $admin = $dataset->id('user/admin-'.$client['firm']);
            $check(3 === count($conversations->forOwner($owner)), 'Three conversations per advisory client.');
            $request = $conversations->find($dataset->id('conversation/'.$key.'/pending'));
            $resolved = $conversations->find($dataset->id('conversation/'.$key.'/resolved'));
            $check(null === $request?->resolvedAt && null !== $resolved?->resolvedAt, 'Pending and resolved requests.');
            $check(null !== $profile && true === $show->execute($admin, $profile->id(), new QuarterPeriod(2026, 3))->record?->closed, 'Own advisory can read closed quarter.');
        }
    }
    $personal = $show->execute($dataset->id('user/independiente'), null, new QuarterPeriod(2026, 4));
    $check(15000 === $personal->calculation->summary->withholdingCents(), 'Personal income includes optional withholding.');
    $vat = $show->execute($dataset->id('user/autonomo-sol'), null, new QuarterPeriod(2026, 4));
    $check(1040 === $vat->calculation->summary->surchargeCents(), 'VAT expense includes optional surcharge.');
    try {
        $show->execute($dataset->id('user/admin-atlantico'), $dataset->id('client/autonomo-sol'), new QuarterPeriod(2026, 3));
        throw new RuntimeException('Cross-firm access allowed.');
    } catch (QuarterNotFound) {
        $check(true, 'Cross-firm quarter hidden.');
    }
    $owner = $dataset->id('user/autonomo-sol');
    $db->executeStatement('UPDATE identity_user SET password_hash = ? WHERE id = ?', ['synthetic-edited-password-hash', $owner]);
    $db->executeStatement('UPDATE client_document SET party_name = ? WHERE id = ?', ['Edición conservada', $dataset->id('document/autonomo-sol/q4-submitted')]);
    $em->clear();
    $edited = $snapshot();
    $fileHashes = [];
    foreach (array_merge(glob($root.'/var/storage/documents/*') ?: [], glob($root.'/var/storage/messages/*') ?: []) as $file) {
        $fileHashes[$file] = hash_file('sha256', $file);
    }
    $check(!$seed()->execute() && $edited === $snapshot(), 'Repeat preserves edits and passwords without duplicates.');
    foreach ($fileHashes as $file => $hash) {
        $check($hash === hash_file('sha256', $file), 'Repeat preserves original files.');
    }
} finally {
    $db->rollBack();
    $em->clear();
    foreach (['documents', 'messages'] as $area) {
        $directory = $root.'/var/storage/'.$area;
        foreach (glob($directory.'/*') ?: [] as $file) {
            if (dirname((string) realpath($file)) !== realpath($directory)) {
                throw new RuntimeException('Unsafe cleanup path.');
            }
            unlink($file);
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
    foreach ([$root.'/var/storage', $root.'/var', $root] as $directory) {
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
    $kernel->shutdown();
}
$check($before === $snapshot(), 'All existing data unchanged after integration rollback.');
echo "Demo data integration checks: $checks passed.\nIsolated fixtures and PDFs removed; existing data unchanged.\n";
