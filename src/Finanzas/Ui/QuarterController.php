<?php

namespace App\Finanzas\Ui;

use App\Cliente\Application\ShowClientHome;
use App\Finanzas\Application\ChangeQuarter;
use App\Finanzas\Application\ShowQuarter;
use App\Finanzas\Domain\InvalidQuarter;
use App\Finanzas\Domain\QuarterChanged;
use App\Finanzas\Domain\QuarterNotFound;
use App\Finanzas\Domain\QuarterPeriod;
use App\Identidad\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class QuarterController extends AbstractController
{
    #[Route('/ingresos', name: 'client_movements', methods: ['GET', 'POST'])]
    #[Route('/clientes/{id}/trimestre', name: 'firm_client_quarter', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-f-]{36}'])]
    public function show(Request $request, ShowQuarter $show, ChangeQuarter $change, ShowClientHome $home, QuarterPresenter $presenter, ?string $id = null): Response
    {
        $user = $this->getUser();
        if (!$user instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }
        $current = $show->currentPeriod();
        $year = $request->query->get('year', (string) $current->year);
        $number = $request->query->get('quarter', (string) $current->number);
        if (!ctype_digit($year) || !ctype_digit($number)) {
            throw $this->createNotFoundException();
        }
        try {
            $period = new QuarterPeriod((int) $year, (int) $number);
            $scope = $request->query->get('scope');
            $view = $show->execute($user->id(), $id, $period, $scope);
            $csrf = 'quarter_'.$view->access->ownerId.'_'.($view->firmId ?? 'personal').'_'.$period->key();
            if ($request->isMethod('POST')) {
                if (!$this->isCsrfTokenValid($csrf, (string) $request->request->get('_csrf_token'))) {
                    $this->addFlash('error', 'La sesión del formulario ha caducado. Vuelve a intentarlo.');
                } else {
                    $action = $request->request->get('intent');
                    if (!in_array($action, ['close', 'reopen'], true)) {
                        throw $this->createNotFoundException();
                    }
                    try {
                        $change->execute($user->id(), $id, $period, $scope, 'close' === $action, (int) $request->request->get('version', 0), (string) $request->request->get('fingerprint', ''));
                        $this->addFlash('success', 'close' === $action ? 'El trimestre está cerrado. Sus cifras quedan guardadas.' : 'El trimestre está abierto. Puedes corregir sus registros.');
                    } catch (QuarterChanged) {
                        $this->addFlash('error', 'Los datos han cambiado. Revisa las cifras actuales antes de continuar.');
                    } catch (InvalidQuarter) {
                        $this->addFlash('error', 'No se puede cerrar: revisa los registros pendientes y los documentos sin fecha.');
                    }
                }

                return $this->redirectToRoute(null === $id ? 'client_movements' : 'firm_client_quarter', array_filter(['id' => $id, 'year' => $period->year, 'quarter' => $period->number, 'scope' => $scope], static fn ($value): bool => null !== $value));
            }
        } catch (QuarterNotFound|InvalidQuarter) {
            throw $this->createNotFoundException();
        }
        $layout = [];
        if (null === $id) {
            $client = $home->execute($user->id());
            $layout = ['section' => 'movimientos', 'email' => $user->getUserIdentifier(), 'name' => $client->name(), 'firmName' => $client->firmName()];
        }

        return $this->render(null === $id ? 'client/movements.html.twig' : 'firm/client_quarter.html.twig', [...$layout, ...$presenter->view($view), 'csrf' => $csrf]);
    }
}
