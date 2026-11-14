<?php

namespace App\Asesoria\Ui;

use App\Asesoria\Application\CreateFirm;
use App\Asesoria\Application\ListFirms;
use App\Asesoria\Domain\InvalidFirm;
use App\Identidad\Domain\EmailAlreadyUsed;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\InvalidFiscalContact;
use App\Shared\Domain\SpanishProvince;
use App\Shared\Ui\FiscalContactForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FirmController extends AbstractController
{
    #[Route('/asesorias', name: 'firms', methods: ['GET'])]
    public function list(ListFirms $listFirms): Response
    {
        $user = $this->getUser();
        if (!$user instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('firm/list.html.twig', [
            'firms' => $listFirms->execute($user->id()),
        ]);
    }

    #[Route('/asesorias/nueva', name: 'firm_new', methods: ['GET', 'POST'])]
    public function create(Request $request, CreateFirm $createFirm): Response
    {
        $name = (string) $request->request->get('name', '');
        $email = (string) $request->request->get('email', '');
        $taxId = (string) $request->request->get('taxId', '');
        $phone = (string) $request->request->get('phone', '');
        $street = (string) $request->request->get('street', '');
        $postalCode = (string) $request->request->get('postalCode', '');
        $municipality = (string) $request->request->get('municipality', '');
        $province = (string) $request->request->get('province', '');
        $error = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('create_firm', (string) $request->request->get('_csrf_token'))) {
                $error = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';
            } else {
                try {
                    $createFirm->execute($name, $email, FiscalContactForm::read($taxId, $phone, $street, $postalCode, $municipality, $province));
                    $this->addFlash('success', 'Le hemos enviado un correo para crear su contraseña.');

                    return $this->redirectToRoute('firms');
                } catch (InvalidFirm) {
                    $error = 'Escribe el nombre de la asesoría.';
                } catch (InvalidFiscalContact $exception) {
                    $error = FiscalContactForm::message($exception);
                } catch (EmailAlreadyUsed) {
                    $error = 'Ese correo ya tiene acceso.';
                }
            }
        }

        return $this->render('firm/new.html.twig', [
            'name' => $name,
            'email' => $email,
            'taxId' => $taxId,
            'phone' => $phone,
            'street' => $street,
            'postalCode' => $postalCode,
            'municipality' => $municipality,
            'province' => $province,
            'provinces' => SpanishProvince::names(),
            'error' => $error,
        ]);
    }
}
