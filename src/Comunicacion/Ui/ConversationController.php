<?php

namespace App\Comunicacion\Ui;

use App\Cliente\Application\ShowClientHome;
use App\Comunicacion\Application\Messaging;
use App\Comunicacion\Domain\ConversationNotFound;
use App\Comunicacion\Domain\InvalidMessage;
use App\Identidad\Domain\Role;
use App\Identidad\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConversationController extends AbstractController
{
    private const ID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    #[Route('/mensajes', name: 'messages', methods: ['GET'])]
    public function index(Messaging $messaging, ConversationPresenter $presenter, ShowClientHome $home): Response
    {
        $actor = $this->actor();
        try {
            $records = $messaging->list($actor->id());
            $recipients = $messaging->recipients($actor->id());
        } catch (ConversationNotFound) {
            throw $this->createNotFoundException();
        }

        return $this->render('communication/list.html.twig', [...$this->layout($actor, $home), 'recipients' => $recipients, 'conversations' => array_map(fn ($record): array => $presenter->view($record, $actor->id()), $records)]);
    }

    #[Route('/mensajes/nuevo', name: 'message_new', methods: ['GET', 'POST'])]
    public function start(Request $request, Messaging $messaging, ShowClientHome $home): Response
    {
        $actor = $this->actor();
        $error = null;
        $subject = (string) $request->request->get('subject', '');
        $text = (string) $request->request->get('text', '');
        $documentId = (string) ($request->isMethod('POST') ? $request->request->get('document', '') : $request->query->get('document', ''));
        $isRequest = '1' === $request->request->get('request');
        try {
            $recipients = $messaging->recipients($actor->id());
            $ownerId = (string) ($request->isMethod('POST') ? $request->request->get('owner', '') : $request->query->get('owner', Role::Client === $actor->role() ? $actor->id() : ''));
            $selected = null;
            foreach ($recipients as $recipient) {
                if ($recipient->userId() === $ownerId) {
                    $selected = $recipient;
                }
            }
            if ('' !== $ownerId && null === $selected && [] !== $recipients) {
                throw new ConversationNotFound();
            }
            $documents = null === $selected ? [] : $messaging->sharedDocuments($actor->id(), $ownerId);
            if ($request->isMethod('POST')) {
                if (!$this->isCsrfTokenValid('new_message', (string) $request->request->get('_csrf_token'))) {
                    $error = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';
                } else {
                    try {
                        $conversation = $messaging->start($actor->id(), $ownerId, $subject, $text, $isRequest, $documentId, self::uploads($request));

                        return $this->redirectToRoute('message_thread', ['id' => $conversation->id]);
                    } catch (InvalidMessage $exception) {
                        $error = $exception->getMessage();
                    }
                }
            }
        } catch (ConversationNotFound) {
            throw $this->createNotFoundException();
        }

        return $this->render('communication/new.html.twig', [...$this->layout($actor, $home), 'recipients' => $recipients, 'selected' => $selected, 'documents' => $documents, 'subject' => $subject, 'text' => $text, 'documentId' => $documentId, 'isRequest' => $isRequest, 'error' => $error]);
    }

    #[Route('/mensajes/{id}', name: 'message_thread', methods: ['GET', 'POST'], requirements: ['id' => self::ID])]
    public function show(string $id, Request $request, Messaging $messaging, ConversationPresenter $presenter, ShowClientHome $home): Response
    {
        $actor = $this->actor();
        $error = null;
        $text = (string) $request->request->get('text', '');
        try {
            // Opening records only the sequence displayed in this request.
            $conversation = $messaging->open($actor->id(), $id);
            if ($request->isMethod('POST')) {
                if (!$this->isCsrfTokenValid('thread_'.$id, (string) $request->request->get('_csrf_token'))) {
                    $error = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';
                } else {
                    try {
                        if ('resolve' === $request->request->get('intent')) {
                            $messaging->resolve($actor->id(), $id);
                        } elseif ('reply' === $request->request->get('intent')) {
                            $messaging->reply($actor->id(), $id, $text, self::uploads($request));
                        } else {
                            throw new InvalidMessage('Elige enviar un mensaje o resolver la solicitud.');
                        }

                        return $this->redirectToRoute('message_thread', ['id' => $id]);
                    } catch (InvalidMessage $exception) {
                        $error = $exception->getMessage();
                    }
                }
            }
        } catch (ConversationNotFound) {
            throw $this->createNotFoundException();
        }

        return $this->render('communication/thread.html.twig', [...$this->layout($actor, $home), 'conversation' => $presenter->view($conversation, $actor->id()), 'text' => $text, 'error' => $error]);
    }

    #[Route('/mensajes/{id}/adjuntos/{fileId}', name: 'message_attachment', methods: ['GET'], requirements: ['id' => self::ID, 'fileId' => self::ID])]
    public function download(string $id, string $fileId, Messaging $messaging): Response
    {
        $actor = $this->actor();
        try {
            $download = $messaging->attachment($actor->id(), $id, $fileId);
        } catch (ConversationNotFound) {
            throw $this->createNotFoundException();
        }
        $file = $download['file'];
        $fallback = preg_replace('/[^\x20-\x7e]/', '_', str_replace('%', '_', $file->name)) ?: 'adjunto';

        return new Response($download['contents'], 200, ['Content-Type' => $file->mediaType, 'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $file->name, $fallback), 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function actor(): SecurityUser
    {
        $actor = $this->getUser();
        if (!$actor instanceof SecurityUser || !in_array($actor->role(), [Role::Client, Role::FirmAdmin, Role::Advisor], true)) {
            throw $this->createAccessDeniedException();
        }

        return $actor;
    }

    /** @return array<string, mixed> */
    private function layout(SecurityUser $actor, ShowClientHome $home): array
    {
        $client = Role::Client === $actor->role();
        $view = $client ? $home->execute($actor->id()) : null;

        return ['clientView' => $client, 'staff' => !$client, 'section' => 'mensajes', 'email' => $actor->getUserIdentifier(), 'name' => $view?->name(), 'firmName' => $view?->firmName()];
    }

    /** @return list<array{name: string, contents: string}> */
    private static function uploads(Request $request): array
    {
        $files = $request->files->all('attachments');
        if (count($files) > 3) {
            throw new InvalidMessage('Puedes adjuntar hasta tres archivos por mensaje.');
        }
        $uploads = [];
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid() || $file->getSize() > 8_000_000) {
                throw new InvalidMessage('Revisa los archivos: PDF, JPEG, PNG o WebP de hasta 8 MB cada uno.');
            }
            $contents = file_get_contents($file->getPathname());
            if (false === $contents) {
                throw new InvalidMessage('No hemos podido leer el archivo adjunto.');
            }
            $uploads[] = ['name' => $file->getClientOriginalName(), 'contents' => $contents];
        }

        return $uploads;
    }
}
