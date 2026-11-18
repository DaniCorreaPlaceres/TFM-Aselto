<?php

namespace App\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\DeploymentLock;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class DeploymentGuard
{
    public function __construct(private DeploymentLock $lock, #[Autowire('%kernel.environment%')] private string $environment)
    {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 8192)]
    public function request(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || 'prod' !== $this->environment) {
            return;
        }
        if (!$this->lock->acquire()) {
            $event->setResponse(new Response('Aselto está en mantenimiento. Vuelve a intentarlo en unos minutos.', 503, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'private, no-store',
                'Retry-After' => '60',
                'X-Aselto-Maintenance' => '1',
            ]));
        }
    }

    #[AsEventListener(event: KernelEvents::TERMINATE, priority: \PHP_INT_MIN)]
    #[AsEventListener(event: ConsoleEvents::TERMINATE, priority: \PHP_INT_MIN)]
    public function release(): void
    {
        $this->lock->release();
    }

    #[AsEventListener(event: ConsoleEvents::COMMAND, priority: 8192)]
    public function command(ConsoleCommandEvent $event): void
    {
        if ('prod' === $this->environment && 'app:security' === $event->getCommand()?->getName() && !$this->lock->acquire()) {
            $event->getOutput()->writeln('maintenance');
            $event->disableCommand();
        }
    }
}
