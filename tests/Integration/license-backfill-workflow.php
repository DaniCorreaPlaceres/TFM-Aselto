<?php

// Run before migrations on an empty, disposable MySQL test database.
require dirname(__DIR__).'/bootstrap.php';

use App\Documento\Domain\Document;
use App\Documento\Infrastructure\Persistence\DoctrineDocumentRepository;
use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Uid\Uuid;

$kernel = new Kernel('test', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
if (!str_ends_with((string) $db->fetchOne('SELECT DATABASE()'), '_test') || [] !== $db->createSchemaManager()->listTableNames()) {
    throw new RuntimeException('Use an empty disposable database ending in _test.');
}
$application = new Application($kernel);
$application->setAutoExit(false);
$migrate = static function (string $version) use ($application, $db, $em): void {
    $output = new BufferedOutput();
    $input = new ArrayInput(['command' => 'doctrine:migrations:migrate', 'version' => $version, '--no-interaction' => true]);
    $input->setInteractive(false);
    if (0 !== $application->run($input, $output)) {
        throw new RuntimeException($output->fetch());
    }
    // MySQL DDL commits implicitly; reset the embedded console's connection lifecycle.
    $db->close();
    $em->clear();
};
$migrate('DoctrineMigrations\\Version20261114030000');
$owner = Uuid::v4()->toRfc4122();
$ids = [Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122()];
try {
    $documents = new DoctrineDocumentRepository($em);
    foreach (['2026-07-31 23:00:00 UTC', '2026-11-30 23:59:59 UTC'] as $i => $at) {
        $documents->save(Document::receive($ids[$i], $owner, null, 'synthetic.pdf', 'synthetic.pdf', 'application/pdf', new DateTimeImmutable($at)));
    }
    $before = $db->fetchAllAssociative('SELECT * FROM client_document ORDER BY id');
    $migrate('latest');
    if ($before !== $db->fetchAllAssociative('SELECT * FROM client_document ORDER BY id') || '2026-08' !== $db->fetchOne('SELECT month FROM document_usage WHERE document_id=?', [$ids[0]]) || '2026-11' !== $db->fetchOne('SELECT month FROM document_usage WHERE document_id=?', [$ids[1]])) {
        throw new RuntimeException('Backfill must preserve all original fields and use Canary calendar months.');
    }
    echo "License backfill: existing originals preserved byte for byte; summer and winter months correct.\n";
} finally {
    $db->executeStatement('DELETE FROM client_document WHERE client_user_id=?', [$owner]);
    try {
        $db->executeStatement('DELETE FROM document_usage WHERE owner_id=?', [$owner]);
    } catch (Doctrine\DBAL\Exception\TableNotFoundException) {
    }
    $kernel->shutdown();
}
