<?php

namespace App\Comunicacion\Ui;

use App\Identidad\Domain\Role;
use App\Identidad\Infrastructure\Security\SecurityUser;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MessageNavigation extends AbstractExtension
{
    public function __construct(private readonly \App\Shared\Application\BrowseRecords $browse, private readonly TokenStorageInterface $tokens, private readonly RequestStack $requests)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('message_attention', $this->attention(...))];
    }

    /** @return array{unread: int, requests: int} */
    public function attention(): array
    {
        $actor = $this->tokens->getToken()?->getUser();
        if (!$actor instanceof SecurityUser || !in_array($actor->role(), [Role::Client, Role::FirmAdmin, Role::Advisor], true)) {
            return ['unread' => 0, 'requests' => 0];
        }
        $request = $this->requests->getCurrentRequest();
        $key = '_message_attention_'.$actor->id();
        if ($request?->attributes->has($key)) {
            return $request->attributes->get($key);
        }
        $counts = $this->browse->attention($actor->id());
        $request?->attributes->set($key, $counts);

        return $counts;
    }
}
