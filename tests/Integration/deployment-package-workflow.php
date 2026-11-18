<?php

// Explicit synthetic archive checks. No database, SMTP, SSH or application data.
require dirname(__DIR__).'/bootstrap.php';
$directory = sys_get_temp_dir().'/aselto-package-check-'.bin2hex(random_bytes(8));
mkdir($directory, 0700);
$sha = str_repeat('1', 40);
$checks = 0;
$check = static function (bool $ok) use (&$checks): void {
    if (!$ok) {
        throw new RuntimeException('Deployment package assertion failed.');
    }
    ++$checks;
};
$make = static function (string $name, array $extra = [], bool $symlink = false) use ($directory, $sha): string {
    $zip = new ZipArchive();
    $path = $directory.'/'.$name.'.zip';
    $zip->open($path, ZipArchive::CREATE);
    $files = ['app/src/example.php' => '<?php // synthetic', 'app/vendor/bootstrap/[...asset].ts' => 'synthetic', 'web/index.php' => '<?php // synthetic'];
    $files = array_merge($files, $extra);
    $hashes = [];
    foreach ($files as $file => $contents) {
        $zip->addFromString($file, $contents);
        $hashes[$file] = hash('sha256', $contents);
    }
    if ($symlink) {
        $zip->setExternalAttributesName('app/src/example.php', ZipArchive::OPSYS_UNIX, 0120777 << 16);
    }
    $zip->addFromString('release.json', json_encode(['sha' => $sha, 'files' => $hashes], JSON_THROW_ON_ERROR));
    $zip->close();

    return $path;
};
$run = static function (string $package, string $name, ?string $hash = null, ?string $expected = null) use ($directory, $sha): int {
    $process = proc_open([PHP_BINARY, dirname(__DIR__, 2).'/bin/deploy/prepare.php', 'unpack', $package, $expected ?? $sha, $hash ?? hash_file('sha256', $package), $directory.'/'.$name], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    foreach ($pipes as $pipe) {
        if ('w' !== stream_get_meta_data($pipe)['mode']) {
            stream_get_contents($pipe);
        }
        fclose($pipe);
    }

    return proc_close($process);
};
$valid = $make('valid');
$check(0 === $run($valid, 'valid-output'));
$check('<?php // synthetic' === file_get_contents($directory.'/valid-output/app/src/example.php'));
$check(1 === $run($valid, 'bad-hash', str_repeat('0', 64)) && !file_exists($directory.'/bad-hash'));
$check(1 === $run($valid, 'bad-sha', null, str_repeat('2', 40)));
$check(1 === $run($make('traversal', ['../outside.php' => 'synthetic']), 'bad-traversal'));
$check(!file_exists($directory.'/outside.php'));
$check(1 === $run($make('private-env', ['app/.env.local' => 'synthetic']), 'bad-env'));
$check(1 === $run($make('symlink', [], true), 'bad-link'));
echo 'Deployment package checks: '.$checks." passed. No external services or data.\n";
