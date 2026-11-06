<?php

namespace App\Identidad\Ui;

use App\Identidad\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends AbstractController
{
    #[Route('/cuenta', name: 'account', methods: ['GET'])]
    public function show(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('identity/account.html.twig', [
            'email' => $user->getUserIdentifier(),
            'role' => RoleLabel::inSpanish($user->role()),
        ]);
    }
}
