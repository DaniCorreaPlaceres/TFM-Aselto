<?php

namespace App\Cliente\Ui;

use App\Cliente\Application\RegisterClient;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\InvalidClient;
use App\Cliente\Domain\UnknownClientKind;
use App\Identidad\Domain\EmailAlreadyUsed;
use App\Shared\Domain\InvalidFiscalContact;
use App\Shared\Domain\SpanishProvince;
use App\Shared\Ui\FiscalContactForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/registro', name: 'client_register', methods: ['GET', 'POST'])]
    public function register(Request $request, RegisterClient $registerClient): Response
    {
        $name = (string) $request->request->get('name', '');
        $email = (string) $request->request->get('email', '');
        $kind = $request->request->getString('kind', $request->isMethod('GET') ? $request->query->getString('kind') : '');
        $taxId = (string) $request->request->get('taxId', '');
        $phone = (string) $request->request->get('phone', '');
        $street = (string) $request->request->get('street', '');
        $postalCode = (string) $request->request->get('postalCode', '');
        $municipality = (string) $request->request->get('municipality', '');
        $province = (string) $request->request->get('province', '');
        $error = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('register_client', (string) $request->request->get('_csrf_token'))) {
                $error = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';
            } else {
                try {
                    $registerClient->execute($name, self::kind($kind), $email, null, FiscalContactForm::read($taxId, $phone, $street, $postalCode, $municipality, $province));
                    $this->addFlash('success', 'Te hemos enviado un correo para crear tu contraseña.');

                    return $this->redirectToRoute('login');
                } catch (UnknownClientKind) {
                    $error = 'Elige autónomo o sociedad.';
                } catch (InvalidClient) {
                    $error = 'Escribe el nombre.';
                } catch (InvalidFiscalContact $exception) {
                    $error = FiscalContactForm::message($exception);
                } catch (EmailAlreadyUsed) {
                    $error = 'Ese correo ya tiene acceso.';
                }
            }
        }

        return $this->render('client/register.html.twig', [
            'name' => $name,
            'email' => $email,
            'kind' => $kind,
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

    private static function kind(string $value): ClientKind
    {
        return match ($value) {
            'autonomo' => ClientKind::SelfEmployed,
            'sociedad' => ClientKind::Company,
            default => throw new UnknownClientKind('The client kind is unknown.'),
        };
    }
}
