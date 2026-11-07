<?php

namespace App\Cliente\Application;

use App\Asesoria\Domain\FirmRepository;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\ClientRepository;
use App\Cliente\Domain\EmailAlreadyUsed;
use App\Cliente\Domain\MissingFirm;
use App\Cliente\Domain\PasswordTooShort;
use App\Identidad\Domain\PasswordHasher;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\Transaction;

final class RegisterClient
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly UserRepository $users,
        private readonly FirmRepository $firms,
        private readonly PasswordHasher $passwords,
        private readonly IdGenerator $ids,
        private readonly Transaction $transaction,
    ) {
    }

    public function execute(string $name, ClientKind $kind, string $email, string $plainPassword, ?string $firmId): Client
    {
        if (mb_strlen($plainPassword) < 8) {
            throw new PasswordTooShort('The password is too short.');
        }

        if (null !== $firmId && null === $this->firms->find($firmId)) {
            throw new MissingFirm('The firm does not exist.');
        }

        if (null !== $this->users->findByEmail($email)) {
            throw new EmailAlreadyUsed('The email is already used.');
        }

        $user = new User(
            $this->ids->next(),
            $email,
            $this->passwords->hash($plainPassword),
            Role::Client,
            $firmId,
        );
        $client = new Client($this->ids->next(), $user->id(), $kind, $name, $firmId);

        $this->transaction->run(function () use ($user, $client): void {
            $this->users->save($user);
            $this->clients->save($client);
        });

        return $client;
    }
}
