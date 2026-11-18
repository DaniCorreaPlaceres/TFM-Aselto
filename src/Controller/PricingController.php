<?php

namespace App\Controller;

use App\Shared\Domain\PlanCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PricingController extends AbstractController
{
    #[Route('/precios', name: 'commercial_pricing', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('commercial/pricing.html.twig', ['freeLimit' => PlanCatalog::FREE_MONTHLY_DOCUMENTS, 'professional' => number_format(PlanCatalog::PROFESSIONAL_CENTS / 100, 2, ',', '.'), 'advisory' => number_format(PlanCatalog::ADVISORY_CLIENT_CENTS / 100, 2, ',', '.')]);
    }
}
