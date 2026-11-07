<?php

namespace App\Asesoria\Ui;

use App\Asesoria\Application\OpenFirmProfile;
use App\Asesoria\Application\SaveFirmProfile;
use App\Asesoria\Domain\FirmProfileDenied;
use App\Asesoria\Domain\MissingFirm;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\InvalidFiscalContact;
use App\Shared\Domain\SpanishProvince;
use App\Shared\Ui\FiscalContactForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FirmProfileController extends AbstractController
{
    #[Route('/asesoria/datos', name: 'firm_profile', methods: ['GET', 'POST'])]
    public function show(Request $request, OpenFirmProfile $openFirmProfile, SaveFirmProfile $saveFirmProfile): Response
    {
        $user = $this->getUser();

        if (!$user instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }

        try {
            $firm = $openFirmProfile->execute($user->id());
        } catch (FirmProfileDenied|MissingFirm) {
            throw $this->createAccessDeniedException();
        }

        $error = null;
        $fields = [
            'taxId' => $firm->contact()->taxId(),
            'phone' => $firm->contact()->phone(),
            'street' => $firm->contact()->street(),
            'postalCode' => $firm->contact()->postalCode(),
            'municipality' => $firm->contact()->municipality(),
            'province' => $firm->contact()->province(),
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('save_firm_profile', (string) $request->request->get('_csrf_token'))) {
                $error = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';
            } else {
                $fields = self::input($request);

                try {
                    $saveFirmProfile->execute($user->id(), FiscalContactForm::read(...array_values($fields)));
                    $this->addFlash('success', 'Los datos fiscales están guardados.');

                    return $this->redirectToRoute('firm_profile');
                } catch (InvalidFiscalContact $exception) {
                    $error = FiscalContactForm::message($exception);
                } catch (FirmProfileDenied) {
                    throw $this->createAccessDeniedException();
                }
            }
        }

        return $this->render('firm/profile.html.twig', [
            'name' => $firm->name(),
            'error' => $error,
            'provinces' => SpanishProvince::names(),
            ...$fields,
        ]);
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
}
