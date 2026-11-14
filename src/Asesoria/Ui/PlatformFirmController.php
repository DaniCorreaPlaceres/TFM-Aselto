<?php

namespace App\Asesoria\Ui;

use App\Asesoria\Application\AdministerFirms;
use App\Asesoria\Domain\FirmChanged;
use App\Asesoria\Domain\FirmProfileDenied;
use App\Asesoria\Domain\InvalidFirm;
use App\Asesoria\Domain\MissingFirm;
use App\Identidad\Domain\EmailAlreadyUsed;
use App\Identidad\Domain\EmailChangeDeliveryFailed;
use App\Identidad\Domain\EmailChangeUnavailable;
use App\Identidad\Domain\InvalidUser;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\InvalidFiscalContact;
use App\Shared\Domain\SpanishProvince;
use App\Shared\Ui\FiscalContactForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class PlatformFirmController extends AbstractController
{
    #[Route('/asesorias/{id}', name: 'platform_firm_detail', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-f-]{36}'])]
    public function detail(string $id, Request $request, AdministerFirms $firms): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERADMIN');
        $user = $this->getUser();
        if (!$user instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }
        try {
            $view = $firms->open($user->id(), $id);
        } catch (FirmProfileDenied|MissingFirm) {
            throw $this->createNotFoundException();
        }
        $contact = $view->firm->contact();
        $values = ['name' => $view->firm->name(), 'taxId' => $contact->taxId(), 'phone' => $contact->phone(), 'street' => $contact->street(), 'postalCode' => $contact->postalCode(), 'municipality' => $contact->municipality(), 'province' => $contact->province()];
        $fingerprint = $request->isMethod('POST') ? $request->request->getString('fingerprint') : $view->fingerprint();
        $email = $request->request->getString('email', $view->emailChange->newEmail ?? '');
        $error = null;
        $status = 200;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('administer_firm_'.$id, $request->request->getString('_csrf_token'))) {
                throw $this->createAccessDeniedException('La sesión del formulario ha caducado.');
            }
            $action = $request->request->getString('action');
            try {
                if ('details' === $action) {
                    foreach (array_keys($values) as $field) {
                        $values[$field] = $request->request->getString($field);
                    }
                    $firms->save($user->id(), $id, $fingerprint, $values['name'], FiscalContactForm::read($values['taxId'], $values['phone'], $values['street'], $values['postalCode'], $values['municipality'], $values['province']));
                    $this->addFlash('success', 'Los datos de la asesoría se han guardado.');
                } elseif ('email' === $action) {
                    $firms->requestEmail($user->id(), $id, $fingerprint, $email);
                    $this->addFlash('success', 'Enlace enviado al nuevo correo. El actual sigue vigente hasta confirmar. Los enlaces de cambio anteriores ya no funcionan.');
                } elseif ('notice' === $action) {
                    $firms->retryNotice($user->id(), $id);
                    $this->addFlash('success', 'Aviso enviado al correo antiguo.');
                } elseif ('cancel' === $action) {
                    $firms->cancelEmail($user->id(), $id, $fingerprint);
                    $this->addFlash('success', 'Cambio pendiente cancelado. Su enlace ya no funciona.');
                } else {
                    throw new BadRequestHttpException('Acción no válida.');
                }

                return $this->redirectToRoute('platform_firm_detail', ['id' => $id]);
            } catch (FirmChanged) {
                $error = 'Los datos han cambiado. Recarga la página y revisa la información antes de guardar.';
                $status = 409;
            } catch (InvalidFiscalContact $exception) {
                $error = FiscalContactForm::message($exception);
            } catch (InvalidFirm) {
                $error = 'Escribe un nombre de hasta 120 caracteres.';
            } catch (InvalidUser) {
                $error = 'Escribe un correo válido de hasta 180 caracteres.';
            } catch (EmailAlreadyUsed) {
                $error = 'Ese correo ya pertenece a una cuenta. No se ha cambiado el acceso.';
            } catch (EmailChangeUnavailable) {
                $error = 'El cambio no está disponible. Comprueba que hay un único admin y que no queda un aviso de seguridad pendiente.';
            } catch (EmailChangeDeliveryFailed) {
                $this->addFlash('error', 'No se pudo enviar el correo. Revisa el estado de la ficha y vuelve a enviarlo; un enlace pendiente anterior ya no es válido.');

                return $this->redirectToRoute('platform_firm_detail', ['id' => $id]);
            }
            $status = 200 === $status ? 422 : $status;
        }
        $response = $this->render('firm/admin_detail.html.twig', $values + compact('view', 'fingerprint', 'email', 'error') + ['provinces' => SpanishProvince::names(), 'now' => time()], new Response(status: $status));
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
