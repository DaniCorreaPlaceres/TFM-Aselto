<?php

namespace App\Identidad\Application;

use App\Identidad\Domain\AccessPurpose;
use App\Identidad\Domain\AccountNotifier;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\Transaction;

final class RequestPasswordReset
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly IssueAccessToken $tokens,
        private readonly Transaction $transaction,
        private readonly AccountNotifier $notifier,
    ) {
    }

    public function execute(string $email): void
    {
        $user = $this->users->findByEmail($email);

        if (null === $user) {
            return;
        }

        $purpose = $user->isActivated() ? AccessPurpose::Reset : AccessPurpose::Activation;
        $lifetime = $user->isActivated() ? '+2 hours' : '+24 hours';
        $rawToken = null;

        $this->transaction->run(function () use ($user, $purpose, $lifetime, &$rawToken): void {
            $rawToken = $this->tokens->issue($user, $purpose, $lifetime);
        });

        if ($user->isActivated()) {
            $this->notifier->sendPasswordReset($user->email(), (string) $rawToken);

            return;
        }

        $this->notifier->sendActivation($user->email(), (string) $rawToken);
    }
}
