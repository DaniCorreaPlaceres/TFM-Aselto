<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    public function __construct(private readonly Connection $connection)
    {
    }

    #[Route('/salud', name: 'health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            $this->connection->fetchOne('SELECT 1');
        } catch (Exception) {
            return new JsonResponse([
                'estado' => 'error',
                'base_de_datos' => 'error',
            ], 503);
        }

        return new JsonResponse([
            'estado' => 'ok',
            'base_de_datos' => 'ok',
        ]);
    }
}
