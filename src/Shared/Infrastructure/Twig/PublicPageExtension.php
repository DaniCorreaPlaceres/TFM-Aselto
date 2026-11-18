<?php

namespace App\Shared\Infrastructure\Twig;

use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PublicPageExtension extends AbstractExtension
{
    public function __construct(private readonly RouterInterface $router)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('public_page', $this->path(...))];
    }

    public function path(string $route): ?string
    {
        if (!str_starts_with($route, 'commercial_') || null === $this->router->getRouteCollection()->get($route)) {
            return null;
        }

        return $this->router->generate($route);
    }
}
