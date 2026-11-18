<?php

// Run only after composer --no-dev and production asset compilation in a clean checkout.
$root = dirname(__DIR__, 2);
$arguments = $_SERVER['argv'] ?? [];
$sha = $arguments[1] ?? '';
$output = $arguments[2] ?? '';
if (!preg_match('/^[a-f0-9]{40}$/', $sha) || !is_dir($output)) {
    exit("Uso: php bin/deploy/build.php COMMIT_SHA DIRECTORIO_SALIDA\n");
}
$installed = json_decode(file_get_contents($root.'/vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);
if (false !== ($installed['dev'] ?? null) || !is_file($root.'/public/assets/manifest.json')) {
    throw new RuntimeException('Production dependencies and compiled assets are required.');
}
$zip = new ZipArchive();
$path = rtrim($output, '/').'/aselto-'.$sha.'.zip';
if (true !== $zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL)) {
    throw new RuntimeException('Package already exists or cannot be created.');
}
$hashes = [];
$add = static function (string $file, string $name) use ($zip, &$hashes): void {
    if (is_link($file) || !is_file($file)) {
        throw new RuntimeException('Only regular package files are accepted.');
    }
    $zip->addFile($file, $name);
    $hashes[$name] = hash_file('sha256', $file);
};
$tree = static function (string $directory, string $prefix) use ($root, $zip, $add): void {
    $zip->addEmptyDir($prefix);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $file) {
        $relative = substr($file->getPathname(), strlen($root.'/'.$directory) + 1);
        if (preg_match('~(^|/)(\.git|\.github|\.gitignore|\.env[^/]*)(/|$)|(^|/)reference\.php$|decrypt\.private\.php$~', $relative)
            || ('config' === $directory && ('jwt' === $relative || str_starts_with($relative, 'jwt/')))
            || ('vendor' === $directory && str_starts_with($relative, 'bin/'))) {
            continue;
        }
        if ($file->isLink()) {
            throw new RuntimeException('Package symlinks are not accepted.');
        }
        if ($file->isDir()) {
            $zip->addEmptyDir($prefix.'/'.$relative);
        } else {
            $add($file->getPathname(), $prefix.'/'.$relative);
        }
    }
};
foreach (['src', 'config', 'migrations', 'templates', 'translations', 'resources', 'assets', 'vendor', 'public', 'openapi', 'docs/marca'] as $directory) {
    if (is_dir($root.'/'.$directory)) {
        $tree($directory, 'app/'.$directory);
    }
}
foreach (['.env', 'composer.json', 'composer.lock', 'symfony.lock', 'importmap.php', 'bin/console'] as $file) {
    $add($root.'/'.$file, 'app/'.$file);
}
$tree('public', 'web');
$add($root.'/docs/deployment/cloud-public-index.php', 'web/index.php');
$add($root.'/docs/deployment/cloud-public.htaccess', 'web/.htaccess');
ksort($hashes);
$zip->addFromString('release.json', json_encode(['sha' => $sha, 'files' => $hashes], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
$zip->close();
file_put_contents($path.'.sha256', hash_file('sha256', $path).'  '.basename($path)."\n");
echo basename($path).' '.count($hashes)." archivos\n";
