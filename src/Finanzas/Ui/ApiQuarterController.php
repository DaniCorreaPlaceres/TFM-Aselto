<?php

namespace App\Finanzas\Ui;

use App\Finanzas\Application\ChangeQuarter;
use App\Finanzas\Application\ShowQuarter;
use App\Finanzas\Domain\InvalidQuarter;
use App\Finanzas\Domain\QuarterChanged;
use App\Finanzas\Domain\QuarterNotFound;
use App\Finanzas\Domain\QuarterPeriod;
use App\Identidad\Domain\Role;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Shared\Ui\ApiError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(requirements: ['year' => '[0-9]{1,4}', 'quarter' => '[0-9]', 'id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
final class ApiQuarterController extends AbstractController
{
    public function __construct(private readonly ShowQuarter $show, private readonly ChangeQuarter $change, private readonly ApiQuarterInput $input, private readonly ApiQuarterPresenter $presenter)
    {
    }

    #[Route('/api/v1/quarters/{year}/{quarter}', name: 'api_quarter', methods: ['GET'])]
    #[Route('/api/v1/advisory/clients/{id}/quarters/{year}/{quarter}', name: 'api_advisory_quarter', methods: ['GET'])]
    #[Route('/api/v1/quarters/{year}/{quarter}/close', name: 'api_quarter_close', methods: ['POST'])]
    #[Route('/api/v1/advisory/clients/{id}/quarters/{year}/{quarter}/close', name: 'api_advisory_quarter_close', methods: ['POST'])]
    #[Route('/api/v1/quarters/{year}/{quarter}/reopen', name: 'api_quarter_reopen', methods: ['POST'])]
    #[Route('/api/v1/advisory/clients/{id}/quarters/{year}/{quarter}/reopen', name: 'api_advisory_quarter_reopen', methods: ['POST'])]
    public function handle(Request $request, int $year, int $quarter, ?string $id = null): JsonResponse
    {
        $route = (string) $request->attributes->get('_route');
        $actor = $this->getUser();
        $roles = str_starts_with($route, 'api_advisory_') ? [Role::FirmAdmin, Role::Advisor] : [Role::Client];
        if (!$actor instanceof SecurityUser || !in_array($actor->role(), $roles, true)) {
            throw $this->createAccessDeniedException();
        }
        try {
            $period = new QuarterPeriod($year, $quarter);
        } catch (InvalidQuarter) {
            return ApiError::response(400, 'invalid_quarter', 'Indica un año entre 1 y 9999 y un trimestre entre 1 y 4.');
        }
        $scope = $this->input->scope($request);
        try {
            if ($request->isMethod('POST')) {
                $close = str_ends_with($route, '_close');
                $data = $this->input->mutation($request, $close);
                $this->change->execute($actor->id(), $id, $period, $scope, $close, $data['version'], $data['fingerprint']);
            }
            $view = $this->show->execute($actor->id(), $id, $period, $scope);

            return new JsonResponse($this->presenter->view($view), headers: ['Cache-Control' => 'private, no-store']);
        } catch (QuarterNotFound) {
            return ApiError::response(404, 'quarter_not_found', 'No encontramos el trimestre solicitado.');
        } catch (QuarterChanged) {
            return ApiError::response(409, 'quarter_changed', 'El trimestre o sus datos han cambiado. Consulta las cifras actuales antes de continuar.');
        } catch (InvalidQuarter) {
            return ApiError::response(422, 'quarter_has_pending_documents', 'Revisa los registros pendientes y los documentos sin fecha antes de cerrar.');
        }
    }
}
