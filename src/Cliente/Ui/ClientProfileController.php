<?php

namespace App\Cliente\Ui;

use App\Cliente\Application\OpenClientFiscalProfile;
use App\Cliente\Application\SaveClientFiscalProfile;
use App\Cliente\Application\ShowClientHome;
use App\Cliente\Domain\ClientProfileLocked;
use App\Cliente\Domain\UnknownClient;
use App\Identidad\Domain\Role;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\InvalidFiscalContact;
use App\Shared\Domain\SpanishProvince;
use App\Shared\Ui\FiscalContactForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientProfileController extends AbstractController
{
    private const ID = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    #[Route('/perfil', name: 'client_profile', methods: ['GET', 'POST'])]
    public function own(Request $request, ShowClientHome $showClientHome, OpenClientFiscalProfile $openClientFiscalProfile, SaveClientFiscalProfile $saveClientFiscalProfile): Response
    {
        $user = $this->client();

        try {
            $access = $openClientFiscalProfile->forOwner($user->id());
        } catch (UnknownClient) {
            throw $this->createNotFoundException();
        }

        $error = null;
        $fields = self::fromContact($access->client()->contact());

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('save_client_profile', (string) $request->request->get('_csrf_token'))) {
                $error = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';
            } else {
                $fields = self::input($request);

                try {
                    $saveClientFiscalProfile->own($user->id(), FiscalContactForm::read(...array_values($fields)));
                    $this->addFlash('success', 'Los datos fiscales están guardados.');

                    return $this->redirectToRoute('client_profile');
                } catch (InvalidFiscalContact $exception) {
                    $error = FiscalContactForm::message($exception);
                }
            }
        }

        $home = $showClientHome->execute($user->id());

        return $this->render('client/profile.html.twig', [
            'section' => '',
            'email' => $user->getUserIdentifier(),
            'name' => $home->name(),
            'firmName' => $home->firmName(),
            'error' => $error,
            'csrf' => 'save_client_profile',
            'provinces' => SpanishProvince::names(),
            ...$fields,
        ]);
    }

    #[Route('/clientes/{id}', name: 'client_fiscal', methods: ['GET', 'POST'], requirements: ['id' => self::ID])]
    public function show(Request $request, string $id, OpenClientFiscalProfile $openClientFiscalProfile, SaveClientFiscalProfile $saveClientFiscalProfile): Response
    {
        $user = $this->staff();

        try {
            $access = $openClientFiscalProfile->forActor($user->id(), $id);
        } catch (UnknownClient) {
            throw $this->createNotFoundException();
        }

        $error = null;
        $fields = self::fromContact($access->client()->contact());

        if ($request->isMethod('POST')) {
            if (!$access->editable()) {
                throw $this->createAccessDeniedException();
            }

            if (!$this->isCsrfTokenValid('save_client_fiscal', (string) $request->request->get('_csrf_token'))) {
                $error = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';
            } else {
                $fields = self::input($request);

                try {
                    $saveClientFiscalProfile->byActor($user->id(), $id, FiscalContactForm::read(...array_values($fields)));
                    $this->addFlash('success', 'Los datos fiscales están guardados.');

                    return $this->redirectToRoute('client_fiscal', ['id' => $id]);
                } catch (InvalidFiscalContact $exception) {
                    $error = FiscalContactForm::message($exception);
                } catch (ClientProfileLocked) {
                    throw $this->createAccessDeniedException();
                }
            }
        }

        return $this->render('client/fiscal.html.twig', [
            'clientName' => $access->client()->name(),
            'quarterClientId' => in_array($user->role(), [Role::FirmAdmin, Role::Advisor], true) ? $id : null,
            'email' => $access->email(),
            'editable' => $access->editable(),
            'error' => $error,
            'csrf' => 'save_client_fiscal',
            'provinces' => SpanishProvince::names(),
            ...$fields,
        ]);
    }

    private function client(): SecurityUser
    {
        $user = $this->getUser();

        if (!$user instanceof SecurityUser || Role::Client !== $user->role()) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function staff(): SecurityUser
    {
        $user = $this->getUser();

        if (!$user instanceof SecurityUser || Role::Client === $user->role()) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    /**
     * @return array{taxId: string, phone: string, street: string, postalCode: string, municipality: string, province: string}
     */
    private static function input(Request $request): array
    {
        return [
            'taxId' => (string) $request->request->get('taxId', ''),
            'phone' => (string) $request->request->get('phone', ''),
            'street' => (string) $request->request->get('street', ''),
            'postalCode' => (string) $request->request->get('postalCode', ''),
            'municipality' => (string) $request->request->get('municipality', ''),
            'province' => (string) $request->request->get('province', ''),
        ];
    }

    /**
     * @return array{taxId: string, phone: string, street: string, postalCode: string, municipality: string, province: string}
     */
    private static function fromContact(\App\Shared\Domain\FiscalContact $contact): array
    {
        return [
            'taxId' => $contact->taxId(),
            'phone' => $contact->phone(),
            'street' => $contact->street(),
            'postalCode' => $contact->postalCode(),
            'municipality' => $contact->municipality(),
            'province' => $contact->province(),
        ];
    }
}
