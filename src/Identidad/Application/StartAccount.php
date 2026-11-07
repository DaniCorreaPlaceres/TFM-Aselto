<?php

namespace App\Identidad\Application;

use App\Identidad\Domain\AccessPurpose;
use App\Identidad\Domain\AccountNotifier;
use App\Identidad\Domain\EmailAlreadyUsed;
use App\Identidad\Domain\PasswordHasher;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\TokenGenerator;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\Transaction;

final class StartAccount
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $passwords,
        private readonly IdGenerator $ids,
        private readonly TokenGenerator $tokenGenerator,
        private readonly IssueAccessToken $tokens,
        private readonly Transaction $transaction,
        private readonly AccountNotifier $notifier,
    ) {
    }

    /**
     * @param null|callable(User): void $afterUser
     */
    public function open(string $email, Role $role, ?string $firmId, ?callable $afterUser = null): User
    {
        if (null !== $this->users->findByEmail($email)) {
            throw new EmailAlreadyUsed('The email is already used.');
        }

        $user = new User(
            $this->ids->next(),
            $email,
            $this->passwords->hash($this->tokenGenerator->next()),
            $role,
            $firmId,
        );
        $rawToken = null;

        $this->transaction->run(function () use ($user, $afterUser, &$rawToken): void {
            $this->users->save($user);
            $rawToken = $this->tokens->issue($user, AccessPurpose::Activation, '+24 hours');

            if (null !== $afterUser) {
                $afterUser($user);
            }
        });

        $this->notifier->sendActivation($user->email(), (string) $rawToken);

        return $user;
    }
}
