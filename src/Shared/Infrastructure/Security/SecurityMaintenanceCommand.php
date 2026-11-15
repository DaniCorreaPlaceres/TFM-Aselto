<?php

namespace App\Shared\Infrastructure\Security;

use App\Shared\Application\SecurityAlertSummary;
use App\Shared\Domain\SecurityAudit;
use App\Shared\Domain\SecurityEvent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:security', description: 'Consulta privada, retención y alertas de seguridad.')]
final class SecurityMaintenanceCommand extends Command
{
    public function __construct(private readonly PrivateSecurityJournal $journal, private readonly SecurityAlertSummary $summary, private readonly SecurityAlertDelivery $delivery, private readonly SecurityAudit $audit)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('action', InputArgument::REQUIRED, 'events, retention o alerts')->addOption('send', null, InputOption::VALUE_NONE, 'Enviar resumen si está habilitado.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $action = $input->getArgument('action');
            if ('retention' === $action) {
                $output->writeln('Archivos retirados: '.$this->journal->purge());
                $this->audit->record(SecurityEvent::RetentionApplied);
            } elseif ('events' === $action) {
                $recent = [];
                foreach ($this->journal->scan() as $entry) {
                    $recent[] = $entry->data();
                    if (count($recent) > 50) {
                        array_shift($recent);
                    }
                }
                $output->writeln(json_encode($recent, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
                $this->audit->record(SecurityEvent::JournalRead);
            } elseif ('alerts' === $action) {
                $output->writeln($input->getOption('send') ? $this->delivery->send() : json_encode($this->summary->summarize($this->journal->scan())['alerts'], JSON_THROW_ON_ERROR));
            } else {
                throw new \InvalidArgumentException('Invalid action.');
            }
            if (!$this->journal->healthy()) {
                throw new \RuntimeException('Security journal unhealthy.');
            }

            return Command::SUCCESS;
        } catch (\Throwable) {
            $output->writeln('<error>No se ha completado el mantenimiento privado de seguridad.</error>');

            return Command::FAILURE;
        }
    }
}
