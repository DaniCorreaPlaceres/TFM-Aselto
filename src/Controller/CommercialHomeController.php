<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CommercialHomeController extends AbstractController
{
    #[Route('/', name: 'commercial_home', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('commercial_home.html.twig', [
            'signedIn' => null !== $this->getUser(),
        ]);
    }
}
