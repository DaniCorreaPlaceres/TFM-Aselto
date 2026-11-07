<?php

namespace App\Identidad\Application;

use App\Identidad\Domain\AccessPurpose;
use App\Identidad\Domain\AccessToken;
use App\Identidad\Domain\AccessTokenRepository;
use App\Identidad\Domain\TokenGenerator;
use App\Identidad\Domain\User;
use App\Shared\Domain\Clock;
use App\Shared\Domain\IdGenerator;

final class IssueAccessToken
{
    public function __construct(
        private readonly AccessTokenRepository $tokens,
        private readonly IdGenerator $ids,
        private readonly TokenGenerator $tokenGenerator,
        private readonly Clock $clock,
    ) {
    }

    public function issue(User $user, AccessPurpose $purpose, string $lifetime): string
    {
        $rawToken = $this->tokenGenerator->next();
        $this->tokens->discardOpen($user->id(), $purpose);
        $this->tokens->save(new AccessToken(
            $this->ids->next(),
            $user->id(),
            hash('sha256', $rawToken),
            $purpose,
            $this->clock->now()->modify($lifetime),
            null,
        ));

        return $rawToken;
    }
}
