<?php

namespace App\Cliente\Infrastructure;

use App\Cliente\Domain\FirmLinkNotifier;
use App\Identidad\Domain\InvitationDeliveryFailed;
use App\Identidad\Infrastructure\Mail\CanonicalAccountUrls;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SymfonyFirmLinkNotifier implements FirmLinkNotifier
{
    public function __construct(private readonly MailerInterface $mailer, private readonly CanonicalAccountUrls $urls, #[Autowire('%env(MAILER_FROM)%')] private readonly string $sender)
    {
    }

    public function send(string $email, string $firmName, string $token): void
    {
        $url = $this->urls->generate('firm_link_accept', $token);
        try {
            $this->mailer->send((new Email())->from($this->sender)->to($email)->subject('Invitación para vincular tu cuenta de Aselto')->text("$firmName te invita a vincular tu cuenta. Inicia sesión con esta dirección y acepta si quieres compartir tus documentos e histórico con su equipo. Tus cierres personales se conservarán; los registros de periodos abiertos pasarán a revisión. No se cambiará tu contraseña.\n\nEl enlace caduca en 24 horas. Un reenvío invalida el anterior. Si no lo esperabas, puedes ignorarlo.\n\n$url\n"));
        } catch (TransportExceptionInterface $error) {
            throw new InvitationDeliveryFailed('Invitation saved; mail delivery failed.', previous: $error);
        }
    }
}
