<?php

namespace App\Cliente\Ui;

use App\Cliente\Application\LinkExistingClient;
use App\Identidad\Domain\InvitationDeliveryFailed;
use App\Identidad\Domain\InvitationUnavailable;
use App\Identidad\Domain\Role;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Shared\Ui\ApiError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FirmLinkController extends AbstractController
{
    #[Route('/clientes/vincular', name: 'firm_link_send', methods: ['GET', 'POST'])]
    public function send(Request $request, LinkExistingClient $links): Response
    {
        $actor = $this->actor();
        if (!in_array($actor->role(), [Role::FirmAdmin, Role::Advisor], true)) {
            throw $this->createAccessDeniedException();
        }
        $error = null;
        $email = $request->request->getString('email');
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('firm_link_send', $request->request->getString('_csrf_token'))) {
                $error = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';
            } else {
                try {
                    $links->send($actor->id(), $email);
                    $this->addFlash('success', 'Invitación enviada. El cliente debe aceptarla antes de compartir sus documentos. Si había un enlace anterior, ha quedado invalidado.');

                    return $this->redirectToRoute('firm_link_send');
                } catch (InvitationUnavailable|\App\Cliente\Domain\UnknownClient) {
                    $error = 'No se puede enviar la invitación. Comprueba que el correo corresponde a una cuenta activada sin asesoría.';
                } catch (InvitationDeliveryFailed) {
                    $error = 'La invitación está guardada, pero el correo no ha podido enviarse. Puedes reenviarla.';
                }
            }
        }

        return $this->render('client/link_send.html.twig', ['email' => $email, 'error' => $error, 'invitations' => $links->sent($actor->id())], new Response(status: null === $error ? 200 : 422, headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/vincular-asesoria/{token}', name: 'firm_link_accept', methods: ['GET', 'POST'], requirements: ['token' => '[a-f0-9]{64}'])]
    public function accept(string $token, Request $request, LinkExistingClient $links): Response
    {
        $actor = $this->actor();
        try {
            $firmName = $links->firmName($actor->id(), $token);
            $error = null;
            if ($request->isMethod('POST')) {
                if (!$this->isCsrfTokenValid('firm_link_accept', $request->request->getString('_csrf_token'))) {
                    $error = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';
                } else {
                    $links->accept($actor->id(), $token);
                    // The new auth version invalidates this session and existing JWTs.
                    $request->getSession()->invalidate();

                    return $this->render('client/link_done.html.twig', [], new Response(headers: ['Cache-Control' => 'private, no-store']));
                }
            }

            return $this->render('client/link_accept.html.twig', compact('firmName', 'error'), new Response(status: null === $error ? 200 : 422, headers: ['Cache-Control' => 'private, no-store']));
        } catch (InvitationUnavailable) {
            throw $this->createNotFoundException('La invitación no está disponible. Entra con la cuenta destinataria o pide un nuevo enlace.');
        }
    }

    #[Route('/api/v1/firm-link-invitations', name: 'api_firm_link_send', methods: ['POST'])]
    public function apiSend(Request $request, LinkExistingClient $links): JsonResponse
    {
        try {
            $input = $request->toArray();
            if (!is_string($input['email'] ?? null)) {
                return ApiError::response(422, 'invalid_email', 'Indica el correo de la cuenta independiente.');
            }
            $links->send($this->actor()->id(), $input['email']);

            return new JsonResponse(['status' => 'invited'], 201, ['Cache-Control' => 'private, no-store']);
        } catch (InvitationUnavailable|\App\Cliente\Domain\UnknownClient) {
            return ApiError::response(404, 'invitation_unavailable', 'No se puede enviar la invitación a esa cuenta.');
        } catch (InvitationDeliveryFailed) {
            return ApiError::response(503, 'delivery_failed', 'Invitación guardada. No se ha podido enviar el correo; puedes reenviarla.');
        } catch (\Symfony\Component\HttpFoundation\Exception\JsonException) {
            return ApiError::response(400, 'invalid_json', 'Envía un objeto JSON válido.');
        }
    }

    #[Route('/api/v1/firm-link-invitations/accept', name: 'api_firm_link_accept', methods: ['POST'])]
    public function apiAccept(Request $request, LinkExistingClient $links): JsonResponse
    {
        try {
            $input = $request->toArray();
            if (!is_string($input['token'] ?? null)) {
                return ApiError::response(422, 'invalid_token', 'Indica el token de invitación.');
            }
            $links->accept($this->actor()->id(), $input['token']);

            return new JsonResponse(['status' => 'linked', 'login_required' => true], headers: ['Cache-Control' => 'private, no-store']);
        } catch (InvitationUnavailable) {
            return ApiError::response(404, 'invitation_unavailable', 'La invitación no está disponible para esta cuenta.');
        } catch (\Symfony\Component\HttpFoundation\Exception\JsonException) {
            return ApiError::response(400, 'invalid_json', 'Envía un objeto JSON válido.');
        }
    }

    private function actor(): SecurityUser
    {
        $actor = $this->getUser();
        if (!$actor instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }

        return $actor;
    }
}
