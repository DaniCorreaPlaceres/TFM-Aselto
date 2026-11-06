<?php

namespace App\Identidad\Ui;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginController extends AbstractController
{
    #[Route('/entrar', name: 'login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authentication): Response
    {
        return $this->render('identity/login.html.twig', [
            'lastEmail' => $authentication->getLastUsername(),
            'hasError' => null !== $authentication->getLastAuthenticationError(),
        ]);
    }

    #[Route('/salir', name: 'logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('La salida la intercepta el cortafuegos.');
    }
}
