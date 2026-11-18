<?php

namespace App\Shared\Ui;

use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Shared\Application\ManageLicenses;
use App\Shared\Domain\LicensePlan;
use App\Shared\Domain\LicenseUnavailable;
use App\Shared\Domain\MonthlyQuota;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LicenseController extends AbstractController
{
    #[Route('/licencias', name: 'platform_licenses', methods: ['GET', 'POST'])]
    public function administer(Request $request, ManageLicenses $licenses): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERADMIN');
        $actor = $this->actor();
        $error = null;
        if ($request->isMethod('POST')) {
            $id = $request->request->getString('subject');
            $kind = $request->request->getString('kind');
            if (!$this->isCsrfTokenValid('license_'.$kind.'_'.$id, $request->request->getString('_csrf_token'))) {
                throw $this->createAccessDeniedException('La sesión del formulario ha caducado.');
            }
            $plan = LicensePlan::tryFrom($request->request->getString('plan'));
            try {
                if (null === $plan) {
                    throw new LicenseUnavailable('Unknown plan.');
                }
                $licenses->assign($actor->id(), $kind, $id, $plan);
                $this->addFlash('success', 'Plan actualizado. Los documentos y el consumo de este mes se conservan.');

                return $this->redirectToRoute('platform_licenses');
            } catch (LicenseUnavailable) {
                $error = 'No se puede asignar ese plan. Revisa el tipo de cuenta y si sigue siendo independiente.';
            }
        }

        return $this->render('licenses/admin.html.twig', ['subjects' => $licenses->subjects($actor->id()), 'error' => $error], new Response(status: null === $error ? 200 : 422, headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/mi-plan', name: 'own_license', methods: ['GET'])]
    public function own(ManageLicenses $licenses): Response
    {
        try {
            $view = $licenses->own($this->actor()->id());
        } catch (LicenseUnavailable) {
            throw $this->createNotFoundException();
        }

        return $this->render('licenses/own.html.twig', ['quota' => $view instanceof MonthlyQuota ? $view : null, 'firm' => is_array($view) ? $view : null], new Response(headers: ['Cache-Control' => 'private, no-store']));
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
