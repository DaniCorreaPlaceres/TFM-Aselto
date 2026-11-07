<?php

namespace App\Identidad\Ui;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginController extends AbstractController
{
    #[Route('/entrar', name: 'login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authentication): Response
    {
        $error = $authentication->getLastAuthenticationError();
        $errorMessage = null;

        if ($error instanceof CustomUserMessageAccountStatusException) {
            $errorMessage = $error->getMessage();
        } elseif (null !== $error) {
            $errorMessage = 'El correo o la contraseña no son correctos.';
        }

        return $this->render('identity/login.html.twig', [
            'lastEmail' => $authentication->getLastUsername(),
            'errorMessage' => $errorMessage,
        ]);
    }

    #[Route('/salir', name: 'logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('La salida la intercepta el cortafuegos.');
    }
}
