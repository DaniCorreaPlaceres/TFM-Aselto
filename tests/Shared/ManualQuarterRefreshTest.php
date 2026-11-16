<?php

namespace App\Tests\Shared;

use App\Finanzas\Infrastructure\Realtime\QuarterUpdateOutbox;
use App\Finanzas\Infrastructure\Realtime\QuarterUpdatePublisher;
use App\Finanzas\Ui\PublishQuarterUpdatesCommand;
use App\Finanzas\Ui\QuarterSubscriptionController;
use App\Identidad\Domain\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mercure\HubInterface;

final class ManualQuarterRefreshTest extends TestCase
{
    public function testManualModeNeverWritesOutboxOrQueriesDatabase(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('getConnection');
        (new QuarterUpdateOutbox($em, false))->record('synthetic-owner', null);
    }

    public function testManualModeNeverConnectsToHubOrDatabaseAndWatchExits(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('getConnection');
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::never())->method('publish');
        $publisher = new QuarterUpdatePublisher($em, $hub, new NullLogger(), false);
        self::assertSame(0, $publisher->publish());
        $command = new CommandTester(new PublishQuarterUpdatesCommand($publisher, false));
        self::assertSame(0, $command->execute(['--watch' => true]));
        self::assertStringContainsString('no se inicia', $command->getDisplay());
    }

    public function testManualModeCannotIssueSubscriptionCredentials(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::never())->method('find');
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::never())->method('getFactory');
        $this->expectException(NotFoundHttpException::class);
        (new QuarterSubscriptionController(false))->subscribe(Request::create('/trimestre/en-vivo/acceso'), $users, $hub);
    }
}
