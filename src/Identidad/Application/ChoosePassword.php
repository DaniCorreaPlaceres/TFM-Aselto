<?php

namespace App\Identidad\Application;

use App\Identidad\Domain\AccessPurpose;
use App\Identidad\Domain\AccessToken;
use App\Identidad\Domain\AccessTokenRepository;
use App\Identidad\Domain\InvalidAccessToken;
use App\Identidad\Domain\PasswordHasher;
use App\Identidad\Domain\PasswordTooShort;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Transaction;

final class ChoosePassword
{
    public function __construct(
        private readonly AccessTokenRepository $tokens,
        private readonly UserRepository $users,
        private readonly PasswordHasher $passwords,
        private readonly Clock $clock,
        private readonly Transaction $transaction,
    ) {
    }

    public function assertUsable(string $rawToken, AccessPurpose $purpose): void
    {
        $this->usableToken($rawToken, $purpose);
    }

    public function execute(string $rawToken, string $plainPassword, AccessPurpose $purpose): void
    {
        $token = $this->usableToken($rawToken, $purpose);

        if (mb_strlen($plainPassword) < 8) {
            throw new PasswordTooShort('The password is too short.');
        }

        $user = $this->users->find($token->userId());

        if (null === $user) {
            throw new InvalidAccessToken('The account does not exist.');
        }

        $this->transaction->run(function () use ($user, $plainPassword, $token): void {
            $this->users->save($user->withPassword($this->passwords->hash($plainPassword)));
            $this->tokens->save($token->use($this->clock->now()));
        });
    }

    private function usableToken(string $rawToken, AccessPurpose $purpose): AccessToken
    {
        if (1 !== preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            throw new InvalidAccessToken('The token is not valid.');
        }

        $token = $this->tokens->findByHash(hash('sha256', $rawToken));

        if (null === $token || !$token->isUsable($this->clock->now(), $purpose)) {
            throw new InvalidAccessToken('The token is not valid.');
        }

        return $token;
    }
}
