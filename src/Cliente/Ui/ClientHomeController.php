<?php

namespace App\Cliente\Ui;

use App\Cliente\Application\ShowClientHome;
use App\Identidad\Domain\Role;
use App\Identidad\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientHomeController extends AbstractController
{
    #[Route('/inicio', name: 'client_home', methods: ['GET'])]
    public function home(ShowClientHome $showClientHome): Response
    {
        return $this->render('client/home.html.twig', $this->view('inicio', $showClientHome));
    }

    #[Route('/ingresos', name: 'client_movements', methods: ['GET'])]
    public function movements(ShowClientHome $showClientHome): Response
    {
        return $this->render('client/movements.html.twig', $this->view('movimientos', $showClientHome));
    }

    #[Route('/documentos', name: 'client_documents', methods: ['GET'])]
    public function documents(ShowClientHome $showClientHome): Response
    {
        return $this->render('client/documents.html.twig', $this->view('documentos', $showClientHome));
    }

    #[Route('/mi-asesoria', name: 'client_firm', methods: ['GET'])]
    public function firm(ShowClientHome $showClientHome): Response
    {
        return $this->render('client/firm.html.twig', $this->view('asesoria', $showClientHome));
    }

    #[Route('/ayuda', name: 'client_help', methods: ['GET'])]
    public function help(ShowClientHome $showClientHome): Response
    {
        return $this->render('client/help.html.twig', $this->view('ayuda', $showClientHome));
    }

    /**
     * @return array{section: string, email: string, name: ?string, firmName: ?string}
     */
    private function view(string $section, ShowClientHome $showClientHome): array
    {
        $user = $this->getUser();

        if (!$user instanceof SecurityUser || Role::Client !== $user->role()) {
            throw $this->createAccessDeniedException();
        }

        $home = $showClientHome->execute($user->id());

        return [
            'section' => $section,
            'email' => $user->getUserIdentifier(),
            'name' => $home->name(),
            'firmName' => $home->firmName(),
        ];
    }
}
