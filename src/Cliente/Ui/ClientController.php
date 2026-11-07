<?php

namespace App\Cliente\Ui;

use App\Cliente\Application\ListClients;
use App\Cliente\Application\RegisterClient;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\InvalidClient;
use App\Cliente\Domain\MissingFirm;
use App\Cliente\Domain\UnknownClientKind;
use App\Identidad\Domain\EmailAlreadyUsed;
use App\Identidad\Domain\Role;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\InvalidFiscalContact;
use App\Shared\Domain\SpanishProvince;
use App\Shared\Ui\FiscalContactForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientController extends AbstractController
{
    #[Route('/clientes', name: 'clients', methods: ['GET', 'POST'])]
    public function show(Request $request, ListClients $listClients, RegisterClient $registerClient): Response
    {
        $current = $this->getUser();

        if (!$current instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }

        $canCreate = Role::Superadmin === $current->role() || Role::FirmAdmin === $current->role();
        $firmId = Role::Superadmin === $current->role() ? null : $current->firmId();

        if (Role::Client === $current->role() || (null === $firmId && Role::Superadmin !== $current->role())) {
            throw $this->createAccessDeniedException();
        }

        $name = (string) $request->request->get('name', '');
        $email = (string) $request->request->get('email', '');
        $kind = (string) $request->request->get('kind', '');
        $taxId = (string) $request->request->get('taxId', '');
        $phone = (string) $request->request->get('phone', '');
        $street = (string) $request->request->get('street', '');
        $postalCode = (string) $request->request->get('postalCode', '');
        $municipality = (string) $request->request->get('municipality', '');
        $province = (string) $request->request->get('province', '');
        $error = null;

        if ($request->isMethod('POST')) {
            if (!$canCreate) {
                throw $this->createAccessDeniedException();
            }

            if (!$this->isCsrfTokenValid('create_client', (string) $request->request->get('_csrf_token'))) {
                $error = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';
            } else {
                try {
                    $registerClient->execute($name, self::kind($kind), $email, $firmId, FiscalContactForm::read($taxId, $phone, $street, $postalCode, $municipality, $province));
                    $this->addFlash('success', 'Le hemos enviado un correo para crear su contraseña.');

                    return $this->redirectToRoute('clients');
                } catch (UnknownClientKind) {
                    $error = 'Elige autónomo o sociedad.';
                } catch (InvalidClient) {
                    $error = 'Escribe el nombre.';
                } catch (InvalidFiscalContact $exception) {
                    $error = FiscalContactForm::message($exception);
                } catch (EmailAlreadyUsed) {
                    $error = 'Ese correo ya tiene acceso.';
                } catch (MissingFirm) {
                    $error = 'No encontramos la asesoría.';
                }
            }
        }

        $clients = [];

        foreach ($listClients->execute($firmId) as $client) {
            $clients[] = [
                'id' => $client->id(),
                'name' => $client->name(),
                'kind' => ClientKindLabel::inSpanish($client->kind()),
                'email' => $client->email(),
            ];
        }

        return $this->render('client/list.html.twig', [
            'clients' => $clients,
            'canCreate' => $canCreate,
            'unlinked' => null === $firmId,
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
