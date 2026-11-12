<?php

namespace App\Shared\Infrastructure\Demo;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-demo', description: 'Carga dos asesorías ficticias para demostrar el TFM en desarrollo.')]
final class SeedDemoDataCommand extends Command
{
    public function __construct(private readonly SeedDemoData $seed)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $created = $this->seed->execute();
        } catch (\RuntimeException $error) {
            $io->error($error->getMessage());

            return Command::FAILURE;
        }
        $io->success($created ? 'Demostración creada: dos asesorías, diez accesos y cinco clientes.' : 'La demostración ya existe. Se conservan sus datos, archivos y contraseñas actuales.');
        $io->note('Guía y accesos en docs/demo.md. Contraseña inicial local: '.DemoDataset::PASSWORD);

        return Command::SUCCESS;
    }
}
