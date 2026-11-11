<?php

namespace App\Finanzas\Ui;

use App\Finanzas\Infrastructure\Realtime\QuarterTopic;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\UserRepository;
use App\Identidad\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Routing\Attribute\Route;

final class QuarterSubscriptionController extends AbstractController
{
    #[Route('/trimestre/en-vivo/acceso', name: 'quarter_subscription', methods: ['GET'])]
    public function subscribe(Request $request, UserRepository $users, HubInterface $hub): JsonResponse
    {
        $security = $this->getUser();
        $actor = $security instanceof SecurityUser ? $users->find($security->id()) : null;
        if (null === $actor || !$actor->isActivated() || !in_array($actor->role(), [Role::Client, Role::FirmAdmin, Role::Advisor], true)) {
            throw $this->createAccessDeniedException();
        }
        $topic = QuarterTopic::forUser($actor->id());
        $factory = $hub->getFactory();
        if (null === $factory) {
            throw new \LogicException('The subscription token factory is not configured.');
        }
        $expires = new \DateTimeImmutable('+60 seconds');
        $token = $factory->create([new Grant([Grant::ACTION_SUBSCRIBE], [$topic])], ['exp' => $expires]);
        $response = new JsonResponse(['url' => '/.well-known/mercure?'.http_build_query(['match' => $topic])], headers: ['Cache-Control' => 'private, no-store']);
        $response->headers->setCookie(Cookie::create($hub->getCookieName(), $token, $expires, '/.well-known/mercure', secure: $request->isSecure(), httpOnly: true, sameSite: Cookie::SAMESITE_STRICT));

        return $response;
    }
}
