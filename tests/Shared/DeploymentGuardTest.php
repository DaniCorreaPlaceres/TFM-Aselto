<?php

namespace App\Tests\Shared;

use App\Shared\Infrastructure\DeploymentLock;
use App\Shared\Infrastructure\Http\DeploymentGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class DeploymentGuardTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/aselto-deployment-test-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/var/share', 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root.'/var/share/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->root.'/var/share');
        rmdir($this->root.'/var');
        rmdir($this->root);
    }

    public function testActiveRequestPreventsExclusiveDeploymentUntilTermination(): void
    {
        $guard = new DeploymentGuard(new DeploymentLock($this->root), 'prod');
        $event = $this->request();
        $guard->request($event);
        self::assertFalse($event->hasResponse());
        $exclusive = fopen($this->root.'/var/share/deployment.lock', 'c+');
        self::assertNotFalse($exclusive);
        self::assertFalse(flock($exclusive, LOCK_EX | LOCK_NB));
        $guard->release();
        self::assertTrue(flock($exclusive, LOCK_EX | LOCK_NB));
        fclose($exclusive);
    }

    public function testMaintenanceBlocksWebAndSecurityCronButNotMigrationCommands(): void
    {
        touch($this->root.'/var/share/deployment.maintenance');
        $guard = new DeploymentGuard(new DeploymentLock($this->root), 'prod');
        $event = $this->request();
        $guard->request($event);
        self::assertSame(503, $event->getResponse()?->getStatusCode());
        self::assertSame('1', $event->getResponse()->headers->get('X-Aselto-Maintenance'));
        $output = new BufferedOutput();
        $cron = new ConsoleCommandEvent(new Command('app:security'), new ArrayInput([]), $output);
        $guard->command($cron);
        self::assertFalse($cron->commandShouldRun());
        self::assertSame("maintenance\n", $output->fetch());
        $migration = new ConsoleCommandEvent(new Command('doctrine:migrations:migrate'), new ArrayInput([]), $output);
        $guard->command($migration);
        self::assertTrue($migration->commandShouldRun());
    }

    public function testExclusiveDeploymentRejectsNewRequestsWithoutWaiting(): void
    {
        $exclusive = fopen($this->root.'/var/share/deployment.lock', 'c+');
        self::assertNotFalse($exclusive);
        flock($exclusive, LOCK_EX);
        $guard = new DeploymentGuard(new DeploymentLock($this->root), 'prod');
        $event = $this->request();
        $guard->request($event);
        self::assertSame(503, $event->getResponse()?->getStatusCode());
        fclose($exclusive);
    }

    public function testDevelopmentIgnoresProductionMaintenanceMarker(): void
    {
        touch($this->root.'/var/share/deployment.maintenance');
        $guard = new DeploymentGuard(new DeploymentLock($this->root), 'dev');
        $event = $this->request();
        $guard->request($event);
        self::assertFalse($event->hasResponse());
    }

    private function request(): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), Request::create('/salud'), HttpKernelInterface::MAIN_REQUEST);
    }
}
