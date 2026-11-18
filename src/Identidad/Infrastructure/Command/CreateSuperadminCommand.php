<?php

namespace App\Identidad\Infrastructure\Command;

use App\Identidad\Application\StartAccount;
use App\Identidad\Domain\EmailAlreadyUsed;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

#[AsCommand(name: 'app:superadmin:create', description: 'Crea un superadmin pendiente de activación por correo, sin datos de demostración.')]
final class CreateSuperadminCommand extends Command
{
    public function __construct(private readonly StartAccount $accounts, private readonly UserRepository $users)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Correo nuevo del administrador de plataforma.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string) $input->getArgument('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Indica un correo válido.');

            return Command::INVALID;
        }

        try {
            if (null !== $this->users->findByEmail($email)) {
                $io->error('Ese correo ya tiene cuenta. No se cambia su rol ni se reenvían enlaces desde este comando.');

                return Command::FAILURE;
            }
            if (!$input->isInteractive()) {
                $io->error('El alta requiere confirmación interactiva desde la consola del servidor.');

                return Command::FAILURE;
            }
            if (!$io->confirm('¿Crear un superadmin para '.$email.' y enviarle un enlace de activación?', false)) {
                $io->note('Alta cancelada.');

                return Command::SUCCESS;
            }
            $this->accounts->open($email, Role::Superadmin, null);
        } catch (EmailAlreadyUsed|UniqueConstraintViolationException) {
            $io->error('El correo ya está registrado. No se ha cambiado su rol.');

            return Command::FAILURE;
        } catch (TransportExceptionInterface) {
            $io->error('La cuenta queda pendiente, pero el envío ha fallado. Revisa SMTP y utiliza Recuperar acceso para solicitar otro enlace.');

            return Command::FAILURE;
        } catch (\Throwable) {
            $io->error('No se ha completado el alta. Revisa la instalación antes de repetirla; no se muestran datos privados del error.');

            return Command::FAILURE;
        }

        $io->success('Cuenta pendiente creada y enlace enviado al transporte de correo. Comprueba la recepción; caduca en 24 horas.');

        return Command::SUCCESS;
    }
}
