<?php

namespace App\Cliente\Ui;

use App\Cliente\Application\AdministerIndependentClients;
use App\Cliente\Domain\ClientChanged;
use App\Cliente\Domain\InvalidClient;
use App\Cliente\Domain\UnknownClient;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\InvalidFiscalContact;
use App\Shared\Domain\SpanishProvince;
use App\Shared\Ui\FiscalContactForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PlatformIndependentClientController extends AbstractController
{
    #[Route('/clientes-independientes/{id}', name: 'platform_independent_client', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-f-]{36}'])]
    public function detail(string $id, Request $request, AdministerIndependentClients $clients): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERADMIN');
        $user = $this->getUser();
        if (!$user instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }
        try {
            $view = $clients->open($user->id(), $id);
        } catch (UnknownClient) {
            throw $this->createNotFoundException();
        }
        $contact = $view->client->contact();
        $values = ['name' => $view->client->name(), 'taxId' => $contact->taxId(), 'phone' => $contact->phone(), 'street' => $contact->street(), 'postalCode' => $contact->postalCode(), 'municipality' => $contact->municipality(), 'province' => $contact->province()];
        $version = $request->isMethod('POST') ? $request->request->getInt('version') : $view->client->version();
        $error = null;
        $status = 422;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('administer_client_'.$id, $request->request->getString('_csrf_token'))) {
                throw $this->createAccessDeniedException('La sesión del formulario ha caducado.');
            }
            foreach (array_keys($values) as $field) {
                $values[$field] = $request->request->getString($field);
            }
            try {
                $clients->save($user->id(), $id, $version, $values['name'], FiscalContactForm::read($values['taxId'], $values['phone'], $values['street'], $values['postalCode'], $values['municipality'], $values['province']));
                $this->addFlash('success', 'Los datos del cliente independiente se han guardado.');

                return $this->redirectToRoute('platform_independent_client', ['id' => $id]);
            } catch (ClientChanged) {
                $error = 'Los datos han cambiado. Recarga la página y revisa la información antes de guardar.';
                $status = 409;
            } catch (InvalidFiscalContact $exception) {
                $error = FiscalContactForm::message($exception);
            } catch (InvalidClient) {
                $error = 'Escribe un nombre de hasta 120 caracteres.';
            } catch (UnknownClient) {
                throw $this->createNotFoundException();
            }
        }
        $response = $this->render('client/admin_detail.html.twig', $values + compact('view', 'version', 'error') + ['kindLabel' => ClientKindLabel::inSpanish($view->client->kind()), 'provinces' => SpanishProvince::names()], new Response(status: null === $error ? 200 : $status));
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
