<?php

namespace App\Asesoria\Ui;

use App\Asesoria\Application\InviteAdvisor;
use App\Asesoria\Application\ListTeam;
use App\Asesoria\Domain\EmailAlreadyUsed;
use App\Asesoria\Domain\InvalidFirm;
use App\Asesoria\Domain\PasswordTooShort;
use App\Identidad\Domain\Role;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Identidad\Ui\RoleLabel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TeamController extends AbstractController
{
    #[Route('/equipo', name: 'team', methods: ['GET', 'POST'])]
    public function show(Request $request, ListTeam $listTeam, InviteAdvisor $inviteAdvisor): Response
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
                    $inviteAdvisor->execute($current->firmId(), $email, (string) $request->request->get('password', ''));
                    $this->addFlash('success', 'El gestor ya puede entrar.');

                    return $this->redirectToRoute('team');
                } catch (PasswordTooShort) {
                    $error = 'La contraseña debe tener al menos 8 caracteres.';
                } catch (EmailAlreadyUsed) {
                    $error = 'Ese correo ya tiene acceso.';
                } catch (InvalidFirm) {
                    $error = 'No encontramos tu asesoría.';
                }
            }
        }

        $team = [];

        foreach ($listTeam->execute($current->firmId()) as $member) {
            $team[] = [
                'email' => $member->email(),
                'role' => RoleLabel::inSpanish($member->role()),
                'isAdvisor' => Role::Advisor === $member->role(),
            ];
        }

        return $this->render('firm/team.html.twig', [
            'team' => $team,
            'email' => $email,
            'error' => $error,
        ]);
    }
}
