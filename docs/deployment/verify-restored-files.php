<?php

// Private CLI helper: never place this file under public_html.
use App\Comunicacion\Infrastructure\Storage\LocalAttachmentStore;
use App\Documento\Infrastructure\Storage\LocalDocumentFileStore;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\Dotenv\Dotenv;

/** @var list<string> $arguments */
$arguments = $_SERVER['argv'] ?? [];
if ('cli' !== PHP_SAPI || 3 !== count($arguments)) {
    exit("Uso privado: php verify-restored-files.php RUTA_COPIA BASE_RESTORE\n");
}

set_error_handler(static function (): never { throw new RuntimeException('Private verification failed.'); });
$connection = null;
try {
    $root = realpath($arguments[1]);
    $expected = $arguments[2];
    if (false === $root || !str_contains(str_replace('\\', '/', $root), '/restore-check-') || !str_ends_with($expected, '_restore')) {
        throw new RuntimeException('Use a dedicated restore copy.');
    }
    require $root.'/vendor/autoload.php';
    (new Dotenv())->bootEnv($root.'/.env');
    $env = static fn (string $name): string => (string) ($_SERVER[$name] ?? $_ENV[$name] ?? (getenv($name) ?: ''));
    if ('prod' !== $env('APP_ENV') || '0' !== $env('APP_DEBUG') || 'null://null' !== $env('MAILER_DSN')
        || '' !== $env('OPENAI_API_KEY') || '0' !== $env('SECURITY_ALERT_EMAIL_ENABLED')) {
        throw new RuntimeException('Unsafe restore environment.');
    }
    $params = (new DsnParser(['mysql' => 'pdo_mysql']))->parse($env('DATABASE_URL'));
    if (($params['dbname'] ?? null) !== $expected || ($params['driver'] ?? null) !== 'pdo_mysql') {
        throw new RuntimeException('Wrong database.');
    }
    $connection = DriverManager::getConnection($params);
    $connection->executeStatement('SET TRANSACTION READ ONLY');
    $connection->beginTransaction();
    if ($expected !== $connection->fetchOne('SELECT DATABASE()')) {
        throw new RuntimeException('Wrong connected database.');
    }
    $documents = new LocalDocumentFileStore($root);
    $attachments = new LocalAttachmentStore($root);
    $counts = ['originals' => 0, 'supporting_files' => 0, 'message_files' => 0, 'failed' => 0];
    $read = static function (string $path, string $type, bool $message = false) use ($documents, $attachments, &$counts): void {
        try {
            $contents = $message ? $attachments->read($path) : $documents->read($path);
            if ('' === $contents) {
                throw new RuntimeException('Empty file.');
            }
            ++$counts[$type];
        } catch (Throwable) {
            ++$counts['failed'];
        }
    };
    foreach ($connection->iterateAssociative('SELECT stored_path, supporting_files FROM client_document') as $row) {
        $read((string) $row['stored_path'], 'originals');
        $files = null === $row['supporting_files'] ? [] : json_decode((string) $row['supporting_files'], true, 32, JSON_THROW_ON_ERROR);
        foreach ($files as $file) {
            $read((string) $file['stored_path'], 'supporting_files');
        }
    }
    foreach ($connection->iterateAssociative('SELECT messages FROM client_conversation') as $row) {
        foreach (json_decode((string) $row['messages'], true, 64, JSON_THROW_ON_ERROR) as $message) {
            foreach ($message['files'] ?? [] as $file) {
                $read((string) $file['path'], 'message_files', true);
            }
        }
    }
    $connection->rollBack();
    echo json_encode(['checked_at_utc' => gmdate('c'), 'database_matches' => true, 'read_only_transaction' => true, 'files' => $counts], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n";
    exit(0 === $counts['failed'] && $counts['originals'] > 0 ? 0 : 1);
} catch (Throwable) {
    if (null !== $connection && $connection->isTransactionActive()) {
        $connection->rollBack();
    }
    fwrite(STDERR, "Verificación no completada. Revisa la ruta/base de restauración, entorno sin envíos y disponibilidad de archivos. No se muestran datos privados.\n");
    exit(1);
}
