<?php

namespace App\Shared\Infrastructure\Http;

use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Shared\Ui\ApiError;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 128)]
final readonly class ResourceLimitListener
{
    private const UPLOADS = ['client_document_new', 'api_document_upload', 'client_document_support', 'api_document_supply', 'message_new', 'message_thread', 'api_conversation_start', 'api_conversation_reply'];

    public function __construct(private ResourceLimits $limits, private TokenStorageInterface $tokens, private Environment $twig)
    {
    }

    public function __invoke(ControllerEvent $event): void
    {
        $actor = $this->tokens->getToken()?->getUser();
        if (!$event->isMainRequest() || !$actor instanceof SecurityUser || !$actor->isActivated()) {
            return;
        }
        $request = $event->getRequest();
        $route = $request->attributes->getString('_route');
        if (str_starts_with($route, '_') || in_array($route, ['quarter_subscription', 'logout', '_logout_main'], true)) {
            return;
        }
        $operation = str_ends_with($route, '_export') ? 'export' : ($request->isMethod('POST') && in_array($route, self::UPLOADS, true) ? 'upload' : (in_array($request->getMethod(), ['GET', 'HEAD'], true) ? 'read' : 'write'));
        $retry = $this->limits->consume($actor, $operation);
        if (0 === $retry) {
            return;
        }
        $message = 'Has realizado demasiadas operaciones. Espera unos minutos y vuelve a intentarlo. No se ha guardado ningún cambio.';
        $headers = ['Retry-After' => (string) $retry, 'Cache-Control' => 'private, no-store'];
        $response = str_starts_with($request->getPathInfo(), '/api/') || '1' === $request->query->get('table')
            ? ApiError::response(429, 'resource_limit_exceeded', $message, $headers)
            : new Response($this->twig->render('security/resource_limit.html.twig', ['message' => $message]), 429, $headers);
        $event->setController(static fn (): Response => $response);
    }
}
