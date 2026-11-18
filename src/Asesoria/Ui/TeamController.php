<?php

namespace App\Asesoria\Ui;

use App\Asesoria\Application\InviteAdvisor;
use App\Asesoria\Domain\InvalidFirm;
use App\Identidad\Domain\EmailAlreadyUsed;
use App\Identidad\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TeamController extends AbstractController
{
    #[Route('/equipo', name: 'team', methods: ['GET', 'POST'])]
    public function show(Request $request, \App\Shared\Ui\RecordTableView $tables, InviteAdvisor $inviteAdvisor): Response
    {
        $current = $this->getUser();

        if (!$current instanceof SecurityUser || null === $current->firmId()) {
            throw $this->createAccessDeniedException();
        }

        $email = (string) $request->request->get('email', '');
        $error = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('invite_advisor', (string) $request->request->get('_csrf_token'))) {
                $error = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';
            } else {
                try {
                    $inviteAdvisor->execute($current->firmId(), $email);
                    $this->addFlash('success', 'Le hemos enviado un correo para crear su contraseña.');

                    return $this->redirectToRoute('team');
                } catch (EmailAlreadyUsed) {
                    $error = 'Ese correo ya tiene acceso.';
                } catch (InvalidFirm) {
                    $error = 'No encontramos tu asesoría.';
                }
            }
        }

        $cells = $tables->initial($current->id(), 'team');

        return $this->render('firm/team.html.twig', [
            'cells' => $cells,
            'email' => $email,
            'error' => $error,
        ]);
    }
}
