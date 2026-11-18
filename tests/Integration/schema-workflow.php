<?php

// Run explicitly against a migrated MySQL database. Synthetic fixtures roll back.
require dirname(__DIR__).'/bootstrap.php';

use App\Asesoria\Domain\Firm;
use App\Asesoria\Infrastructure\Persistence\DoctrineFirmRepository;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Infrastructure\Persistence\DoctrineClientRepository;
use App\Documento\Application\DocumentForm;
use App\Documento\Domain\Document;
use App\Documento\Infrastructure\Persistence\DoctrineDocumentRepository;
use App\Finanzas\Infrastructure\Realtime\QuarterUpdateOutbox;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Kernel;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\SchemaValidator;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Uid\Uuid;

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$tool = new SchemaTool($em);
$metadata = $em->getMetadataFactory()->getAllMetadata();
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
};
$uuid = static fn (): string => Uuid::v4()->toRfc4122();
$ownerId = $uuid();
$firmId = $uuid();
$documentId = $uuid();

try {
    // Use the console lifecycle so Migrations excludes its own version table.
    $application = new Application($kernel);
    $application->setAutoExit(false);
    $output = new BufferedOutput();
    $status = $application->run(new ArrayInput(['command' => 'doctrine:schema:validate']), $output);
    $check(0 === $status, 'Console validation must succeed: '.$output->fetch());
    $check([] === (new SchemaValidator($em))->validateMapping(), 'Mappings must be valid.');
    $sql = $tool->getUpdateSchemaSql($metadata);
    $check([] === $sql, 'Migrated schema must require no DDL: '.implode('; ', $sql));
    $schema = $tool->getSchemaFromMetadata($metadata);
    $check($schema->hasTable('quarter_update_outbox'), 'The SQL outbox must participate in schema comparisons.');
    $manager = $db->createSchemaManager();
    $actual = $manager->introspectTable('quarter_update_outbox');
    $check(['owner_id', 'scope'] === $actual->getPrimaryKey()?->getColumns(), 'Outbox composite key must be preserved.');
    $check($actual->hasColumn('revision') && $actual->hasColumn('published_revision'), 'Pending and published revisions must remain available.');
    $expected = clone $schema->getTable('quarter_update_outbox');
    $expected->dropColumn('published_revision');
    $check(!$manager->createComparator()->compareTables($actual, $expected)->isEmpty(), 'Outbox structure must not be hidden from drift detection.');

    $db->beginTransaction();
    $firms = new DoctrineFirmRepository($em);
    $users = new DoctrineUserRepository($em);
    $clients = new DoctrineClientRepository($em);
    $documents = new DoctrineDocumentRepository($em);
    $firms->save(new Firm($firmId, 'Asesoría ficticia de esquema'));
    $users->save(new User($ownerId, $ownerId.'@example.test', 'synthetic-hash', Role::Client, $firmId, true));
    $clients->save(new Client($uuid(), $ownerId, ClientKind::SelfEmployed, 'Cliente ficticio de esquema', $firmId));
    $document = Document::receive($documentId, $ownerId, $firmId, 'synthetic.pdf', 'synthetic.pdf', 'application/pdf', new DateTimeImmutable())->saveDraft(DocumentForm::read('ingreso', '2026-11-01', 'Ejemplo', '00000000T', '100', '21'));
    $documents->save($document);
    $outbox = new QuarterUpdateOutbox($em);
    $outbox->record($ownerId, $firmId);
    $outbox->record($ownerId, $firmId);
    $before = $db->fetchAssociative('SELECT * FROM quarter_update_outbox WHERE owner_id = ?', [$ownerId]);
    $check(2 === (int) $before['revision'] && 0 === (int) $before['published_revision'], 'Repeated changes must remain pending in the same outbox row.');
    $check([] === $tool->getUpdateSchemaSql($metadata), 'Populated schema must still require no DDL.');
    $check($before === $db->fetchAssociative('SELECT * FROM quarter_update_outbox WHERE owner_id = ?', [$ownerId]), 'Schema diagnosis must preserve pending notifications exactly.');
    $em->clear();
    $check($firmId === $users->find($ownerId)?->firmId(), 'Mapped identities must remain readable.');
    $check(10000 === $documents->find($documentId)?->baseCents(), 'Financial data must survive diagnosis and ORM reload.');
    $check('2026-11-01' === $documents->find($documentId)?->issuedOn()?->format('Y-m-d'), 'Immutable dates must retain their value.');
} finally {
    if ($db->isTransactionActive()) {
        $db->rollBack();
    }
    $kernel->shutdown();
}

echo $checks." schema checks passed.\n";
