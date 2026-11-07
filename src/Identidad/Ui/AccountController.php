<?php

namespace App\Identidad\Ui;

use App\Identidad\Domain\Role;
use App\Identidad\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends AbstractController
{
    #[Route('/cuenta', name: 'account', methods: ['GET'])]
    public function show(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }

        if (Role::Client === $user->role()) {
            return $this->redirectToRoute('client_home');
        }

        [$destination, $secondary] = match ($user->role()) {
            Role::Superadmin => [['route' => 'firms', 'label' => 'Asesorías'], ['route' => 'clients', 'label' => 'Clientes']],
            Role::FirmAdmin => [['route' => 'clients', 'label' => 'Clientes'], ['route' => 'team', 'label' => 'Equipo']],
            Role::Advisor => [['route' => 'clients', 'label' => 'Clientes'], null],
        };

        return $this->render('identity/account.html.twig', [
            'email' => $user->getUserIdentifier(),
            'role' => RoleLabel::inSpanish($user->role()),
            'destination' => $destination,
            'secondary' => $secondary,
            'fiscal' => Role::FirmAdmin === $user->role() ? ['route' => 'firm_profile', 'label' => 'Datos fiscales'] : null,
        ]);
    }
}
