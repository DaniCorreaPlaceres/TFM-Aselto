<?php

namespace App\Cliente\Ui;

use App\Cliente\Application\ShowClientFirmContact;
use App\Cliente\Application\ShowClientHome;
use App\Finanzas\Application\ShowQuarter;
use App\Identidad\Domain\Role;
use App\Identidad\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientHomeController extends AbstractController
{
    #[Route('/inicio', name: 'client_home', methods: ['GET'])]
    public function home(Request $request, ShowClientHome $showClientHome, \App\Shared\Application\BrowseRecords $browse, ShowQuarter $quarters): Response
    {
        $view = $this->view('inicio', $showClientHome);
        $user = $this->getUser();
        $pending = [];

        if (!$user instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }
        $page = $browse->execute($user->id(), 'pending', new \App\Shared\Domain\RecordCriteria(limit: 5));
        foreach ($page->rows as $document) {
            $pending[] = ['name' => $document['name'], 'status' => $document['status'], 'href' => $this->generateUrl('client_document', ['id' => $document['id']])];
        }
        $counts = $browse->attention($user->id());
        $view['unreadConversations'] = $counts['unread'];
        $view['openRequests'] = $counts['requests'];
        $view['pendingTotal'] = $page->filtered;
        $view['pending'] = $pending;
        $quarter = $quarters->execute($user->id(), null, $quarters->currentPeriod());
        $view['quarter'] = $quarter;
        $view['summary'] = $quarter->record?->closed ? $quarter->record->snapshot : $quarter->calculation->summary;

        if ('1' === $request->headers->get('X-Quarter-Fragment')) {
            return $this->render('client/_home_financial.html.twig', $view, new Response(headers: ['Cache-Control' => 'private, no-store']));
        }

        return $this->render('client/home.html.twig', $view);
    }

    #[Route('/mi-asesoria', name: 'client_firm', methods: ['GET'])]
    public function firm(ShowClientHome $showClientHome, ShowClientFirmContact $contact): Response
    {
        $view = $this->view('asesoria', $showClientHome);
        $actor = $this->getUser();
        if (!$actor instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('client/firm.html.twig', [...$view, 'firmContact' => $contact->execute($actor->id())], new Response(headers: ['Cache-Control' => 'private, no-store']));
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
