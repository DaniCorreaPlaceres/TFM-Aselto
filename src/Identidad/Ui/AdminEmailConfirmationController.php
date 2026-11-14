<?php

namespace App\Identidad\Ui;

use App\Asesoria\Application\AdministerFirms;
use App\Identidad\Domain\EmailAlreadyUsed;
use App\Identidad\Domain\EmailChangeDeliveryFailed;
use App\Identidad\Domain\EmailChangeUnavailable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminEmailConfirmationController extends AbstractController
{
    #[Route('/confirmar-correo-admin/{token}', name: 'admin_email_confirm', methods: ['GET', 'POST'], requirements: ['token' => '[a-f0-9]{64}'])]
    public function confirm(string $token, Request $request, AdministerFirms $firms): Response
    {
        $change = null;
        $done = false;
        $error = null;
        $status = 200;
        try {
            $change = $firms->inspect($token);
            if ($request->isMethod('POST')) {
                if (!$this->isCsrfTokenValid('confirm_admin_email_'.$change->userId, $request->request->getString('_csrf_token'))) {
                    $error = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';
                    $status = 422;
                } else {
                    try {
                        $firms->confirmEmail($token);
                        $done = true;
                    } catch (EmailChangeDeliveryFailed) {
                        $done = true;
                        $error = 'El correo ya se ha cambiado. El aviso de seguridad al correo anterior está pendiente de reenvío por el administrador de Aselto.';
                    }
                }
            }
        } catch (EmailChangeUnavailable|EmailAlreadyUsed) {
            $change = null;
            $error = 'El enlace ha caducado, fue sustituido, ya se usó o el correo ya no está disponible. Pide al administrador de Aselto que revise el cambio.';
            $status = 410;
        }
        $response = $this->render('identity/admin_email_confirm.html.twig', compact('change', 'done', 'error'), new Response(status: $status));
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
