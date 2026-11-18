<?php

namespace App\Identidad\Infrastructure\Security;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class ActivatedUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof SecurityUser && !$user->isActivated()) {
            throw new CustomUserMessageAccountStatusException('Todavía no has creado tu contraseña. Revisa tu correo.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
