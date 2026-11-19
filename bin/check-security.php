<?php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$workflows = [
    'api-documents', 'api-authentication', 'api-communication', 'api-quarters',
    'document-upload', 'messaging', 'quarter',
    'platform-firms', 'platform-independent-clients', 'platform-invitations',
    'canonical-account-links', 'authentication-rate-limits', 'authentication-limit-storage',
    'password-policy', 'session-expiration', 'logout-csrf',
    'browser-security-headers', 'resource-limits', 'security-event-logging', 'firm-link',
];

try {
    if ('1' !== getenv('SECURITY_CHECKS_DISPOSABLE')) {
        throw new RuntimeException('Explicit disposable database opt-in is required.');
    }
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
    if ('dev' !== ($_SERVER['APP_ENV'] ?? null)) {
        throw new RuntimeException('Security workflows require the explicit dev kernel on a disposable database.');
    }
    $database = basename((string) parse_url($_SERVER['DATABASE_URL'] ?? '', PHP_URL_PATH));
    if (1 !== preg_match('/^[a-z0-9_]+_security_ci$/', $database)) {
        throw new RuntimeException('Database name must end in _security_ci.');
    }
    $selected = $workflows;
    if (isset($argv[1])) {
        $name = str_starts_with($argv[1], '--only=') ? substr($argv[1], 7) : '';
        if (!in_array($name, $workflows, true) || isset($argv[2])) {
            throw new RuntimeException('Unknown security workflow selection.');
        }
        $selected = [$name];
    }
    $safeEnvironment = ['MAILER_DSN' => 'null://null', 'OPENAI_API_KEY' => '', 'SECURITY_ALERT_EMAIL_ENABLED' => '0', 'MERCURE_URL' => 'http://127.0.0.1:1/.well-known/mercure'];
    foreach ($safeEnvironment as $key => $value) {
        putenv($key.'='.$value);
        $_SERVER[$key] = $_ENV[$key] = $value;
    }
    $assertEmpty = static function () use ($database): void {
        $kernel = new Kernel('dev', true);
        $kernel->boot();
        try {
            $connection = $kernel->getContainer()->get('doctrine')->getConnection();
            if ($database !== $connection->fetchOne('SELECT DATABASE()')) {
                throw new RuntimeException('Actual database does not match the disposable destination.');
            }
            $tables = $connection->createSchemaManager()->listTableNames();
            if (!in_array('identity_user', $tables, true)) {
                throw new RuntimeException('Migrate the disposable database first.');
            }
            foreach ($tables as $table) {
                if ('doctrine_migration_versions' !== $table && 0 !== (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$connection->quoteIdentifier($table))) {
                    throw new RuntimeException('Security workflows require an empty migrated database.');
                }
            }
        } finally {
            $kernel->shutdown();
        }
    };
    $assertEmpty();
    foreach ($selected as $name) {
        fwrite(STDOUT, 'Security workflow: '.$name."\n");
        $process = new Process([PHP_BINARY, dirname(__DIR__).'/tests/Integration/'.$name.'-workflow.php'], dirname(__DIR__), $safeEnvironment);
        $process->setTimeout(300);
        $status = $process->run(static function (string $type, string $buffer): void {
            fwrite(Process::ERR === $type ? STDERR : STDOUT, $buffer);
        });
        if (0 !== $status) {
            fwrite(STDERR, 'Security workflow failed: '.$name."\n");
            exit(1);
        }
        $assertEmpty();
    }
    fwrite(STDOUT, 'Security workflows passed: '.count($selected).".\n");
} catch (Throwable $failure) {
    fwrite(STDERR, "Security checks stopped; verify the disposable setup or the failing workflow.\n");
    exit(1);
}
