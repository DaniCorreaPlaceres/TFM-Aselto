<?php

namespace App\Identidad\Infrastructure\Mail;

use App\Identidad\Domain\InvitationDeliveryFailed;
use App\Identidad\Domain\InvitationNotifier;
use App\Identidad\Domain\InvitationTarget;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SymfonyInvitationNotifier implements InvitationNotifier
{
    public function __construct(private readonly MailerInterface $mailer, private readonly CanonicalAccountUrls $urls, #[Autowire('%env(MAILER_FROM)%')] private readonly string $sender)
    {
    }

    public function send(string $email, InvitationTarget $target, string $token): void
    {
        $label = match ($target) {
            InvitationTarget::Advisory => 'tu asesoría y su cuenta de administrador',
            InvitationTarget::SelfEmployed => 'tu cuenta de autónomo sin asesoría',
            InvitationTarget::Company => 'tu cuenta de empresa sin asesoría',
        };
        $link = $this->urls->generate('invitation_accept', $token);
        try {
            $this->mailer->send((new Email())->from($this->sender)->to($email)->subject('Invitación para registrarte en Aselto')->text("Te invitamos a registrar $label. Completa tus datos y crea tu contraseña. El enlace es de uso único y caduca en 24 horas. Si recibes un reenvío, utiliza el enlace más reciente.\n\n$link\n"));
        } catch (TransportExceptionInterface $error) {
            throw new InvitationDeliveryFailed('Invitation saved; mail delivery failed.', previous: $error);
        }
    }
}
