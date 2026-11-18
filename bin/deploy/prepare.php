<?php

// Private server helper. Modes: unpack or database-config. Never print credentials.
/** @var list<string> $arguments */
$arguments = $_SERVER['argv'] ?? [];
$mode = $arguments[1] ?? '';
$createdClient = false;
try {
    if ('unpack' === $mode) {
        [$script, $mode, $package, $expectedSha, $expectedHash, $destination] = $arguments;
        if (!preg_match('/^[a-f0-9]{40}$/', $expectedSha) || !preg_match('/^[a-f0-9]{64}$/', $expectedHash)
            || !hash_equals($expectedHash, hash_file('sha256', $package)) || file_exists($destination)) {
            throw new RuntimeException('Package identity or destination is invalid.');
        }
        $zip = new ZipArchive();
        if (true !== $zip->open($package)) {
            throw new RuntimeException('Cannot read package.');
        }
        $manifest = json_decode($zip->getFromName('release.json'), true, 512, JSON_THROW_ON_ERROR);
        if (($manifest['sha'] ?? null) !== $expectedSha || !is_array($manifest['files'] ?? null) || [] === $manifest['files']) {
            throw new RuntimeException('Invalid package manifest.');
        }
        $seen = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = $zip->getNameIndex($i);
            if (isset($seen[$name]) || preg_match('~(^|/)\.{1,2}(/|$)~', $name) || str_contains($name, '\\') || str_starts_with($name, '/')
                || (0 !== preg_match('/[\x00-\x1f\x7f]/', $name))) {
                throw new RuntimeException('Unsafe package entry.');
            }
            $seen[$name] = true;
            $zip->getExternalAttributesIndex($i, $system, $attributes);
            if (0120000 === (($attributes >> 16) & 0170000)) {
                throw new RuntimeException('Symlinks are forbidden.');
            }
            if (str_ends_with($name, '/')) {
                if (!preg_match('~^(app|web)/~', $name)) {
                    throw new RuntimeException('Invalid package directory.');
                }
                continue;
            }
            if ('release.json' === $name) {
                continue;
            }
            if (!preg_match('~^(app|web)/~', $name) || !isset($manifest['files'][$name])
                || preg_match('~^app/(var/|config/jwt/|\.env\.(local|prod|dev|test))|decrypt\.private\.php$~', $name)
                || !hash_equals($manifest['files'][$name], hash('sha256', $zip->getFromIndex($i)))) {
                throw new RuntimeException('Package content is not accepted.');
            }
        }
        foreach ($manifest['files'] as $name => $hash) {
            if (!isset($seen[$name])) {
                throw new RuntimeException('Missing package content.');
            }
        }
        mkdir($destination, 0700);
        if (!$zip->extractTo($destination)) {
            throw new RuntimeException('Cannot unpack package.');
        }
        $zip->close();
    } elseif ('database-config' === $mode) {
        $project = $arguments[2];
        $directory = $arguments[3];
        require $project.'/vendor/autoload.php';
        (new Symfony\Component\Dotenv\Dotenv())->bootEnv($project.'/.env');
        $dsn = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? '';
        $params = (new Doctrine\DBAL\Tools\DsnParser(['mysql' => 'pdo_mysql']))->parse($dsn);
        if ('pdo_mysql' !== ($params['driver'] ?? null) || !preg_match('/^[a-zA-Z0-9_]+$/', $params['dbname'] ?? '')) {
            throw new RuntimeException('Unsupported database configuration.');
        }
        $escape = static fn (string $value): string => '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t']).'"';
        $config = "[client]\n";
        foreach (['host' => 'host', 'port' => 'port', 'user' => 'user', 'password' => 'password'] as $key => $option) {
            if (isset($params[$key])) {
                $config .= $option.'='.$escape((string) $params[$key])."\n";
            }
        }
        $handle = fopen($directory.'/database-client.cnf', 'x');
        if (false === $handle) {
            throw new RuntimeException('Cannot prepare database client.');
        }
        $createdClient = true;
        chmod($directory.'/database-client.cnf', 0600);
        fwrite($handle, $config);
        fclose($handle);
        file_put_contents($directory.'/database-name', $params['dbname']);
    } else {
        exit("Private deploy helper: unpack | database-config\n");
    }
} catch (Throwable) {
    if ($createdClient && isset($directory)) {
        unlink($directory.'/database-client.cnf');
    }
    fwrite(STDERR, "No se ha completado la preparación privada del despliegue. No se muestran credenciales.\n");
    exit(1);
}
