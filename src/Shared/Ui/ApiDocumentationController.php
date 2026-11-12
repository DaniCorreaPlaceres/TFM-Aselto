<?php

namespace App\Shared\Ui;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiDocumentationController extends AbstractController
{
    #[Route('/api/docs', name: 'api_docs', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('api/docs.html.twig', [], new Response(headers: ['Cache-Control' => 'no-store']));
    }

    #[Route('/api/docs/openapi.yaml', name: 'api_docs_contract', methods: ['GET'])]
    public function contract(#[Autowire('%kernel.project_dir%')] string $projectDirectory): Response
    {
        return new Response(file_get_contents($projectDirectory.'/openapi/openapi.yaml'), headers: [
            'Content-Type' => 'application/yaml; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
