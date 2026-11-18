<?php

namespace App\Identidad\Ui;

use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Shared\Ui\ApiError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ApiIdentityController extends AbstractController
{
    #[Route('/api/v1/auth/login', name: 'api_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // The JSON login authenticator handles this route before the controller.
        return ApiError::response(401, 'invalid_credentials', 'El correo o la contraseña no son correctos.');
    }

    #[Route('/api/v1/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof SecurityUser) {
            return ApiError::response(401, 'token_missing', 'Envía tu token en la cabecera Authorization: Bearer.', ['WWW-Authenticate' => 'Bearer']);
        }

        return new JsonResponse(['id' => $user->id(), 'email' => $user->getUserIdentifier(), 'role' => $user->role()->value, 'firm_id' => $user->firmId(), 'activated' => $user->isActivated()], headers: ['Cache-Control' => 'private, no-store']);
    }
}
