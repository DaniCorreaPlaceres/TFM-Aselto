<?php

namespace App\Identidad\Infrastructure\Mail;

use App\Identidad\Domain\EmailChangeDeliveryFailed;
use App\Identidad\Domain\EmailChangeNotifier;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SymfonyEmailChangeNotifier implements EmailChangeNotifier
{
    public function __construct(private readonly MailerInterface $mailer, private readonly UrlGeneratorInterface $urls)
    {
    }

    public function request(string $email, string $token): void
    {
        $url = $this->urls->generate('admin_email_confirm', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->send($email, 'Confirma tu nuevo correo en Aselto', "El administrador de Aselto ha solicitado usar esta dirección para tu cuenta de admin de asesoría. El correo actual sigue vigente hasta que confirmes. Este enlace es de uso único y caduca en 24 horas. Utiliza siempre el último enlace recibido.\n\n$url\n\nSi no esperabas este cambio, no lo confirmes y contacta con Aselto.");
    }

    public function confirmed(string $oldEmail): void
    {
        $this->send($oldEmail, 'Se ha cambiado el correo de tu cuenta en Aselto', 'Se ha confirmado un cambio de correo de tu cuenta de admin de asesoría solicitado por el administrador de Aselto. Esta dirección ya no permite acceder a la cuenta y los accesos anteriores se han invalidado. Si no reconoces el cambio, contacta con Aselto.');
    }

    private function send(string $email, string $subject, string $text): void
    {
        try {
            $this->mailer->send((new Email())->from('Aselto <no-responder@aselto.test>')->to($email)->subject($subject)->text($text));
        } catch (TransportExceptionInterface $error) {
            throw new EmailChangeDeliveryFailed('Email delivery failed.', previous: $error);
        }
    }
}
