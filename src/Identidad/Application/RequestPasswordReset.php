<?php

namespace App\Identidad\Application;

use App\Identidad\Domain\AccessPurpose;
use App\Identidad\Domain\AccountNotifier;
use App\Identidad\Domain\AccountTransaction;
use App\Identidad\Domain\UserRepository;

final class RequestPasswordReset
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly IssueAccessToken $tokens,
        private readonly AccountTransaction $transaction,
        private readonly AccountNotifier $notifier,
    ) {
    }

    public function execute(string $email): void
    {
        $user = $this->users->findByEmail($email);

        if (null === $user) {
            return;
        }

        $rawToken = null;
        $recipient = null;

        $this->transaction->forUser($user->id(), function () use ($user, &$recipient, &$rawToken): void {
            $current = $this->users->find($user->id());
            if (null === $current || $current->email() !== $user->email() || $current->authVersion() !== $user->authVersion()) {
                return;
            }
            $recipient = $current;
            $rawToken = $this->tokens->issue($current, $current->isActivated() ? AccessPurpose::Reset : AccessPurpose::Activation, $current->isActivated() ? '+2 hours' : '+24 hours');
        });

        if (null === $recipient || null === $rawToken) {
            return;
        }
        if ($recipient->isActivated()) {
            $this->notifier->sendPasswordReset($recipient->email(), $rawToken);

            return;
        }

        $this->notifier->sendActivation($recipient->email(), $rawToken);
    }
}
