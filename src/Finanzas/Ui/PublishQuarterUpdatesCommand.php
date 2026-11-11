<?php

namespace App\Finanzas\Ui;

use App\Finanzas\Infrastructure\Realtime\QuarterUpdatePublisher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:quarter-updates:publish', description: 'Publish committed quarter notifications and retry pending deliveries.')]
final class PublishQuarterUpdatesCommand extends Command
{
    public function __construct(private readonly QuarterUpdatePublisher $publisher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('watch', null, InputOption::VALUE_NONE, 'Keep retrying every two seconds.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        while (true) {
            $count = $this->publisher->publish();
            if (!$input->getOption('watch')) {
                $output->writeln(sprintf('%d notifications published.', $count));
                break;
            }
            usleep(2_000_000);
        }

        return Command::SUCCESS;
    }
}
