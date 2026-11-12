<?php

namespace App\Comunicacion\Ui;

use App\Comunicacion\Domain\ConversationNotFound;
use App\Comunicacion\Domain\InvalidMessage;
use App\Shared\Ui\ApiError;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -32)]
final class ApiConversationExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!preg_match('#^/api/v1/(?:conversations|conversation-recipients)(?:/|$)#', $event->getRequest()->getPathInfo())) {
            return;
        }
        $error = $event->getThrowable();
        if ($error instanceof ConversationNotFound) {
            $event->setResponse(ApiError::response(404, 'conversation_not_found', 'No encontramos la conversación, el destinatario o el adjunto solicitado.'));
        } elseif ($error instanceof InvalidMessage) {
            $event->setResponse(ApiError::response(422, 'invalid_message', $error->getMessage()));
        }
    }
}
