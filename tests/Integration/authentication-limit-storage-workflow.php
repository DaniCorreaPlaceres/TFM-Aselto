<?php

// Local concurrency check with an isolated namespace, filesystem cache and flock.
require dirname(__DIR__).'/bootstrap.php';

use App\Identidad\Infrastructure\Security\AuthenticationRateLimits;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\Yaml\Yaml;

$worker = '--worker' === ($argv[1] ?? null);
$run = $worker ? ($argv[2] ?? '') : bin2hex(random_bytes(8));
if (1 !== preg_match('/^[a-f0-9]{16}$/', $run)) {
    throw new RuntimeException('Invalid test namespace.');
}
$directory = dirname(__DIR__, 2).'/var/authentication-limit-storage-check';
if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
    throw new RuntimeException('Cannot create isolated test storage.');
}
$pool = new FilesystemAdapter('test'.$run, 0, $directory);
$locks = new LockFactory(new FlockStore($directory));
$config = Yaml::parseFile(dirname(__DIR__, 2).'/config/packages/rate_limiter.yaml')['framework']['rate_limiter'];
$factories = [];
foreach ($config as $name => $options) {
    $factories[$name] = new RateLimiterFactory(['id' => $name, ...array_intersect_key($options, array_flip(['policy', 'limit', 'interval']))], new CacheStorage($pool), $locks);
}
$limits = new AuthenticationRateLimits($factories['authentication_ip'], $factories['authentication_identity'], $factories['recovery_ip'], $factories['recovery_identity'], $run);
if ($worker) {
    $accepted = 0;
    for ($i = 0; $i < 5; ++$i) {
        $accepted += 0 === $limits->login('192.0.2.1', 'synthetic@example.test') ? 1 : 0;
    }
    echo json_encode(['accepted' => $accepted], JSON_THROW_ON_ERROR);
    exit(0);
}
$processes = [];
try {
    for ($i = 0; $i < 6; ++$i) {
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $run], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start synthetic worker.');
        }
        fclose($pipes[0]);
        $processes[] = [$process, $pipes[1], $pipes[2]];
    }
    $accepted = 0;
    foreach ($processes as [$process, $stdout, $stderr]) {
        $output = stream_get_contents($stdout);
        $errors = stream_get_contents($stderr);
        fclose($stdout);
        fclose($stderr);
        if (0 !== proc_close($process)) {
            throw new RuntimeException('Synthetic worker failed: '.$errors);
        }
        $accepted += json_decode($output, true, flags: JSON_THROW_ON_ERROR)['accepted'];
    }
    $processes = [];
    if (5 !== $accepted || 0 === $limits->login('192.0.2.1', 'synthetic@example.test')) {
        throw new RuntimeException('Workers must share one five-attempt budget.');
    }
    echo "Authentication limiter storage: 6 concurrent processes, 30 attempts, 5 accepted, 25 limited; parent observes the persisted limit.\n";
} finally {
    foreach ($processes as [$process]) {
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
    }
    $pool->clear(); // Only this random test namespace; never clear application counters.
}
