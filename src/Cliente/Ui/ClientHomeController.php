<?php

namespace App\Cliente\Ui;

use App\Cliente\Application\ShowClientHome;
use App\Documento\Application\ListClientDocuments;
use App\Documento\Ui\DocumentLabel;
use App\Finanzas\Application\ShowQuarter;
use App\Identidad\Domain\Role;
use App\Identidad\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientHomeController extends AbstractController
{
    #[Route('/inicio', name: 'client_home', methods: ['GET'])]
    public function home(ShowClientHome $showClientHome, ListClientDocuments $listClientDocuments, ShowQuarter $quarters): Response
    {
        $view = $this->view('inicio', $showClientHome);
        $user = $this->getUser();
        $pending = [];

        if (!$user instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }
        foreach ($listClientDocuments->pending($user->id()) as $document) {
            $pending[] = [
                'name' => $document->partyName() ?: $document->originalName(),
                'status' => DocumentLabel::forDocument($document),
                'href' => $this->generateUrl('client_document', ['id' => $document->id()]),
            ];
        }

        $view['pending'] = $pending;
        $quarter = $quarters->execute($user->id(), null, $quarters->currentPeriod());
        $view['quarter'] = $quarter;
        $view['summary'] = $quarter->record?->closed ? $quarter->record->snapshot : $quarter->calculation->summary;

        return $this->render('client/home.html.twig', $view);
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
