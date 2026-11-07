<?php

namespace App\Identidad\Infrastructure\Command;

use App\Asesoria\Domain\Firm;
use App\Asesoria\Domain\FirmRepository;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\ClientRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Identidad\Infrastructure\Security\SecurityUser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed-dev-access', description: 'Crea los accesos locales de desarrollo.')]
final class SeedDevAccessCommand extends Command
{
    private const PASSWORD = 'aselto-dev';
    private const FIRM_ID = '00000000-0000-4000-8000-000000000001';

    public function __construct(
        private readonly UserRepository $users,
        private readonly FirmRepository $firms,
        private readonly ClientRepository $clients,
        private readonly UserPasswordHasherInterface $hasher,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ('dev' !== $this->environment) {
            $io->error('Este comando solo se ejecuta en desarrollo.');

            return Command::FAILURE;
        }

        if (null === $this->firms->find(self::FIRM_ID)) {
            $this->firms->save(new Firm(self::FIRM_ID, 'Asesoría de prueba'));
        }

        foreach ($this->accounts() as $account) {
            if (null !== $this->users->findByEmail($account['email'])) {
                continue;
            }

            $placeholder = SecurityUser::fromDomain(new User(
                $account['id'],
                $account['email'],
                'pending',
                $account['role'],
                $account['firmId'],
            ));

            $this->users->save(new User(
                $account['id'],
                $account['email'],
                $this->hasher->hashPassword($placeholder, self::PASSWORD),
                $account['role'],
                $account['firmId'],
                true,
            ));
        }

        $client = $this->users->findByEmail('cliente@example.test');

        if (null !== $client && null === $this->clients->findByUserId($client->id())) {
            $this->clients->save(new Client(
                '00000000-0000-4000-8000-000000000021',
                $client->id(),
                ClientKind::SelfEmployed,
                'Cliente de prueba',
                null,
            ));
        }

        $io->success('Accesos locales listos. La contraseña de desarrollo es '.self::PASSWORD.'.');

        return Command::SUCCESS;
    }

    /**
     * @return list<array{id: string, email: string, role: Role, firmId: ?string}>
     */
    private function accounts(): array
    {
        return [
            ['id' => '00000000-0000-4000-8000-000000000011', 'email' => 'superadmin@example.test', 'role' => Role::Superadmin, 'firmId' => null],
            ['id' => '00000000-0000-4000-8000-000000000012', 'email' => 'admin@example.test', 'role' => Role::FirmAdmin, 'firmId' => self::FIRM_ID],
            ['id' => '00000000-0000-4000-8000-000000000013', 'email' => 'gestor@example.test', 'role' => Role::Advisor, 'firmId' => self::FIRM_ID],
            ['id' => '00000000-0000-4000-8000-000000000014', 'email' => 'cliente@example.test', 'role' => Role::Client, 'firmId' => null],
        ];
    }
}
