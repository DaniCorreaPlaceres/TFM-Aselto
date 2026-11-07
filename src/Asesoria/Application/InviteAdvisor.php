<?php

namespace App\Asesoria\Application;

use App\Asesoria\Domain\EmailAlreadyUsed;
use App\Asesoria\Domain\FirmRepository;
use App\Asesoria\Domain\InvalidFirm;
use App\Asesoria\Domain\PasswordTooShort;
use App\Identidad\Domain\PasswordHasher;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\Transaction;

final class InviteAdvisor
{
    public function __construct(
        private readonly FirmRepository $firms,
        private readonly UserRepository $users,
        private readonly PasswordHasher $passwords,
        private readonly IdGenerator $ids,
        private readonly Transaction $transaction,
    ) {
    }

    public function execute(string $firmId, string $email, string $plainPassword): void
    {
        if (null === $this->firms->find($firmId)) {
            throw new InvalidFirm('The firm does not exist.');
        }

        if (mb_strlen($plainPassword) < 8) {
            throw new PasswordTooShort('The password is too short.');
        }

        if (null !== $this->users->findByEmail($email)) {
            throw new EmailAlreadyUsed('The email is already used.');
        }

        $advisor = new User(
            $this->ids->next(),
            $email,
            $this->passwords->hash($plainPassword),
            Role::Advisor,
            $firmId,
        );

        $this->transaction->run(function () use ($advisor): void {
            $this->users->save($advisor);
        });
    }
}
