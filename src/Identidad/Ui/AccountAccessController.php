<?php

namespace App\Identidad\Ui;

use App\Identidad\Application\ChoosePassword;
use App\Identidad\Application\RequestPasswordReset;
use App\Identidad\Domain\AccessPurpose;
use App\Identidad\Domain\InvalidAccessToken;
use App\Identidad\Domain\PasswordTooShort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccountAccessController extends AbstractController
{
    #[Route('/recuperar', name: 'password_reset_request', methods: ['GET', 'POST'])]
    public function request(Request $request, RequestPasswordReset $reset): Response
    {
        $email = (string) $request->request->get('email', '');
        $error = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('request_password', (string) $request->request->get('_csrf_token'))) {
                $error = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';
            } else {
                $reset->execute($email);
                $this->addFlash('success', 'Si el correo está dado de alta, te hemos enviado un enlace.');

                return $this->redirectToRoute('login');
            }
        }

        return $this->render('identity/request_password.html.twig', [
            'email' => $email,
            'error' => $error,
        ]);
    }

    #[Route('/activar/{token}', name: 'account_activate', methods: ['GET', 'POST'], requirements: ['token' => '[a-f0-9]{64}'])]
    public function activate(string $token, Request $request, ChoosePassword $choosePassword): Response
    {
        return $this->choose($request, $token, AccessPurpose::Activation, $choosePassword, 'Confirma tu correo', 'Ya puedes entrar.');
    }

    #[Route('/recuperar/{token}', name: 'password_reset', methods: ['GET', 'POST'], requirements: ['token' => '[a-f0-9]{64}'])]
    public function reset(string $token, Request $request, ChoosePassword $choosePassword): Response
    {
        return $this->choose($request, $token, AccessPurpose::Reset, $choosePassword, 'Crea una contraseña nueva', 'Ya puedes entrar con la contraseña nueva.');
    }

    private function choose(Request $request, string $token, AccessPurpose $purpose, ChoosePassword $choosePassword, string $title, string $success): Response
    {
        $error = null;
        $showForm = true;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('choose_password', (string) $request->request->get('_csrf_token'))) {
                $error = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';
            } else {
                try {
                    $choosePassword->execute($token, (string) $request->request->get('password', ''), $purpose);
                    $this->addFlash('success', $success);

                    return $this->redirectToRoute('login');
                } catch (InvalidAccessToken) {
                    $error = 'Este enlace ya no vale. Pide otro.';
                    $showForm = false;
                } catch (PasswordTooShort) {
                    $error = 'La contraseña debe tener al menos 8 caracteres.';
                }
            }
        } else {
            try {
                $choosePassword->assertUsable($token, $purpose);
            } catch (InvalidAccessToken) {
                $error = 'Este enlace ya no vale. Pide otro.';
                $showForm = false;
            }
        }

        return $this->render('identity/choose_password.html.twig', [
            'title' => $title,
            'error' => $error,
            'showForm' => $showForm,
        ]);
    }
}
