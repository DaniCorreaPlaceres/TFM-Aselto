<?php

namespace App\Shared\Infrastructure\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:version', description: 'Muestra la versión del producto Aselto.')]
final class ProductVersionCommand extends Command
{
    public function __construct(#[Autowire('%app.version%')] private readonly string $productVersion)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->productVersion);

        return Command::SUCCESS;
    }
}
