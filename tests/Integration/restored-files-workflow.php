<?php

// Explicit synthetic check: only the dedicated disposable MariaDB container is used.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$dsn = 'mysql://probe:synthetic-probe@aselto-restore-files-probe:3306/aselto_files_probe_restore?serverVersion=11.8.9-MariaDB';
$params = (new Doctrine\DBAL\Tools\DsnParser(['mysql' => 'pdo_mysql']))->parse($dsn);
for ($attempt = 0;; ++$attempt) {
    try {
        $db = Doctrine\DBAL\DriverManager::getConnection($params);
        $db->fetchOne('SELECT 1');
        break;
    } catch (Throwable $error) {
        if ($attempt >= 20) {
            throw $error;
        } usleep(500000);
    }
}
if (0 !== (int) $db->fetchOne('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')) {
    throw new RuntimeException('Probe database must be empty');
}
$root = sys_get_temp_dir().'/restore-check-files-'.bin2hex(random_bytes(6)).'/aselto';
if (file_exists($root)) {
    throw new RuntimeException('Probe root already exists');
}
mkdir($root.'/var/storage/documents', 0700, true);
mkdir($root.'/var/storage/messages', 0700, true);
symlink(dirname(__DIR__, 2).'/vendor', $root.'/vendor');
file_put_contents($root.'/.env', "APP_ENV=prod\nAPP_DEBUG=0\n");
$doc = 'documents/00000000-0000-4000-8000-000000000001.pdf';
$support = 'documents/00000000-0000-4000-8000-000000000002.pdf';
$message = '00000000-0000-4000-8000-000000000003.pdf';
foreach ([$doc, $support] as $path) {
    file_put_contents($root.'/var/storage/'.$path, "%PDF-1.4\nsynthetic file bytes\n");
}
file_put_contents($root.'/var/storage/messages/'.$message, "%PDF-1.4\nsynthetic attachment bytes\n");
$db->executeStatement('CREATE TABLE client_document (stored_path VARCHAR(255), supporting_files JSON)');
$db->executeStatement('CREATE TABLE client_conversation (messages JSON)');
$db->insert('client_document', ['stored_path' => $doc, 'supporting_files' => json_encode([['stored_path' => $support]], JSON_THROW_ON_ERROR)]);
$db->insert('client_conversation', ['messages' => json_encode([['files' => [['path' => $message]]]], JSON_THROW_ON_ERROR)]);
$env = array_merge(getenv(), ['APP_ENV' => 'prod', 'APP_DEBUG' => '0', 'DATABASE_URL' => $dsn, 'MAILER_DSN' => 'null://null', 'OPENAI_API_KEY' => '', 'SECURITY_ALERT_EMAIL_ENABLED' => '0']);
$run = function (string $expected, array $overrides = []) use ($root, $env): array {
    $p = proc_open([PHP_BINARY, dirname(__DIR__, 2).'/docs/deployment/verify-restored-files.php', $root, $expected], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, array_merge($env, $overrides));
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($p), $stdout, $stderr];
};
$checks = 0;
$check = function (bool $ok) use (&$checks): void { if (!$ok) { throw new RuntimeException('Probe assertion failed'); } ++$checks; };
[$status, $out] = $run('aselto_files_probe_restore');
$check(0 === $status);
$report = json_decode($out, true, 8, JSON_THROW_ON_ERROR);
$check(['originals' => 1, 'supporting_files' => 1, 'message_files' => 1, 'failed' => 0] === $report['files']);
[$status, $out, $err] = $run('wrong_restore');
$check(1 === $status && '' === $out && !str_contains($err, 'synthetic-probe'));
[$status, $out] = $run('aselto_files_probe_restore', ['MAILER_DSN' => 'smtp://127.0.0.1:1']);
$check(1 === $status && '' === $out);
[$status, $out] = $run('aselto_files_probe_restore', ['OPENAI_API_KEY' => 'synthetic-key']);
$check(1 === $status && '' === $out);
[$status, $out] = $run('aselto_files_probe_restore', ['SECURITY_ALERT_EMAIL_ENABLED' => '1']);
$check(1 === $status && '' === $out);
unlink($root.'/var/storage/'.$doc);
[$status, $out] = $run('aselto_files_probe_restore');
$report = json_decode($out, true, 8, JSON_THROW_ON_ERROR);
$check(1 === $status && 1 === $report['files']['failed']);
$check(1 === (int) $db->fetchOne('SELECT COUNT(*) FROM client_document'));
$db->executeStatement('DROP TABLE client_document');
$db->executeStatement('DROP TABLE client_conversation');
echo "Synthetic restore file checks: $checks passed. Disposable database only; no SMTP or AI.\n";
