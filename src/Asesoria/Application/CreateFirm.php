<?php

namespace App\Asesoria\Application;

use App\Asesoria\Domain\EmailAlreadyUsed;
use App\Asesoria\Domain\Firm;
use App\Asesoria\Domain\FirmRepository;
use App\Asesoria\Domain\PasswordTooShort;
use App\Identidad\Domain\PasswordHasher;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\Transaction;

final class CreateFirm
{
    public function __construct(
        private readonly FirmRepository $firms,
        private readonly UserRepository $users,
        private readonly PasswordHasher $passwords,
        private readonly IdGenerator $ids,
        private readonly Transaction $transaction,
    ) {
    }

    public function execute(string $name, string $adminEmail, string $plainPassword): Firm
    {
        $this->guardPassword($plainPassword);

        if (null !== $this->users->findByEmail($adminEmail)) {
            throw new EmailAlreadyUsed('The email is already used.');
        }

        $firm = new Firm($this->ids->next(), $name);
        $admin = new User(
            $this->ids->next(),
            $adminEmail,
            $this->passwords->hash($plainPassword),
            Role::FirmAdmin,
            $firm->id(),
        );

        $this->transaction->run(function () use ($firm, $admin): void {
            $this->firms->save($firm);
            $this->users->save($admin);
        });

        return $firm;
    }

    private function guardPassword(string $plainPassword): void
    {
        if (mb_strlen($plainPassword) < 8) {
            throw new PasswordTooShort('The password is too short.');
        }
    }
}
