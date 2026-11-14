<?php

namespace App\Identidad\Ui;

use App\Asesoria\Domain\InvalidFirm;
use App\Cliente\Domain\InvalidClient;
use App\Identidad\Application\PlatformInvitations;
use App\Identidad\Domain\EmailAlreadyUsed;
use App\Identidad\Domain\InvalidUser;
use App\Identidad\Domain\InvitationAlreadyExists;
use App\Identidad\Domain\InvitationDeliveryFailed;
use App\Identidad\Domain\InvitationTarget;
use App\Identidad\Domain\InvitationUnavailable;
use App\Identidad\Domain\PasswordTooShort;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\InvalidFiscalContact;
use App\Shared\Domain\SpanishProvince;
use App\Shared\Ui\FiscalContactForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PlatformInvitationController extends AbstractController
{
    private const TARGETS = ['advisory' => 'Asesoría (cuenta de admin)', 'self_employed' => 'Autónomo sin asesoría', 'company' => 'Empresa sin asesoría'];

    #[Route('/invitaciones', name: 'platform_invitations', methods: ['GET'])]
    public function list(PlatformInvitations $invitations): Response
    {
        return $this->privateResponse($this->render('identity/invitations.html.twig', ['invitations' => $invitations->list($this->actorId()), 'targets' => self::TARGETS]));
    }

    #[Route('/invitaciones/nueva', name: 'platform_invitation_new', methods: ['GET', 'POST'])]
    public function send(Request $request, PlatformInvitations $invitations): Response
    {
        $this->actorId();
        $email = $request->request->getString('email');
        $target = $request->request->getString('target', $request->query->getString('target', 'advisory'));
        $error = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('platform_invite', $request->request->getString('_csrf_token'))) {
                $error = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';
            } elseif (null === $kind = InvitationTarget::tryFrom($target)) {
                $error = 'Elige el tipo de cuenta.';
            } else {
                try {
                    $invitations->send($this->actorId(), $email, $kind);
                    $this->addFlash('success', 'Invitación enviada. El destinatario completará sus datos al aceptar.');

                    return $this->redirectToRoute('platform_invitations');
                } catch (InvitationAlreadyExists) {
                    $error = 'Ya existe una invitación para ese correo. Consulta su estado y utiliza Reenviar si está disponible.';
                } catch (EmailAlreadyUsed) {
                    $error = 'Ese correo ya tiene una cuenta. No se ha cambiado su acceso.';
                } catch (InvalidUser) {
                    $error = 'Escribe un correo válido de hasta 180 caracteres.';
                } catch (InvitationDeliveryFailed) {
                    return $this->deliveryFailed();
                }
            }
        }

        return $this->privateResponse($this->render('identity/invitation_new.html.twig', compact('email', 'target', 'error') + ['targets' => self::TARGETS], new Response(status: null === $error ? 200 : 422)));
    }

    #[Route('/invitaciones/{id}/reenviar', name: 'platform_invitation_resend', methods: ['POST'])]
    public function resend(string $id, Request $request, PlatformInvitations $invitations): Response
    {
        $actor = $this->actorId();
        if (!$this->isCsrfTokenValid('resend_invitation_'.$id, $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('La sesión del formulario ha caducado.');
        }
        try {
            $invitations->resend($actor, $id);
            $this->addFlash('success', 'Invitación reenviada. El enlace anterior ya no funciona.');
        } catch (EmailAlreadyUsed|InvitationUnavailable) {
            $this->addFlash('error', 'La invitación ya no se puede reenviar. No se ha cambiado ninguna cuenta.');
        } catch (InvitationDeliveryFailed) {
            return $this->deliveryFailed();
        }

        return $this->redirectToRoute('platform_invitations');
    }

    #[Route('/invitacion/{token}', name: 'invitation_accept', methods: ['GET', 'POST'], requirements: ['token' => '[a-f0-9]{64}'])]
    public function accept(string $token, Request $request, PlatformInvitations $invitations): Response
    {
        try {
            $invitation = $invitations->inspect($token);
        } catch (InvitationUnavailable) {
            return $this->unavailable();
        }
        $values = [];
        foreach (['name', 'taxId', 'phone', 'street', 'postalCode', 'municipality', 'province'] as $field) {
            $values[$field] = $request->request->getString($field);
        }
        $error = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('accept_invitation_'.$invitation->id, $request->request->getString('_csrf_token'))) {
                $error = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';
            } elseif ($request->request->getString('password') !== $request->request->getString('passwordConfirmation')) {
                $error = 'Las contraseñas no coinciden.';
            } else {
                try {
                    $contact = FiscalContactForm::read($values['taxId'], $values['phone'], $values['street'], $values['postalCode'], $values['municipality'], $values['province']);
                    $invitations->accept($token, $values['name'], $contact, $request->request->getString('password'));
                    $this->addFlash('success', 'Tu cuenta está creada. Ya puedes entrar con tu correo y contraseña.');

                    return $this->privateResponse($this->redirectToRoute('login'));
                } catch (InvitationUnavailable|EmailAlreadyUsed) {
                    return $this->unavailable();
                } catch (InvalidFiscalContact $exception) {
                    $error = FiscalContactForm::message($exception);
                } catch (InvalidFirm|InvalidClient) {
                    $error = 'Escribe un nombre válido de hasta 120 caracteres.';
                } catch (PasswordTooShort) {
                    $error = 'La contraseña debe tener al menos 8 caracteres.';
                }
            }
        }

        return $this->privateResponse($this->render('identity/invitation_accept.html.twig', $values + ['invitation' => $invitation, 'targetLabel' => self::TARGETS[$invitation->target->value], 'error' => $error, 'provinces' => SpanishProvince::names()], new Response(status: null === $error ? 200 : 422)));
    }

    private function actorId(): string
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERADMIN');
        $user = $this->getUser();
        if (!$user instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }

        return $user->id();
    }

    private function unavailable(): Response
    {
        return $this->privateResponse($this->render('identity/invitation_unavailable.html.twig', [], new Response(status: 410)));
    }

    private function deliveryFailed(): Response
    {
        $this->addFlash('error', 'La invitación se ha guardado, pero no se pudo enviar el correo. Puedes intentar reenviarla desde el listado. El enlace anterior ya no es válido.');

        return $this->redirectToRoute('platform_invitations');
    }

    private function privateResponse(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
