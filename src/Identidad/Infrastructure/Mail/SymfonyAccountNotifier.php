<?php

namespace App\Identidad\Infrastructure\Mail;

use App\Identidad\Domain\AccountNotifier;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SymfonyAccountNotifier implements AccountNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function sendActivation(string $email, string $token): void
    {
        $link = $this->urls->generate('account_activate', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->send(
            $email,
            'Confirma tu correo en Aselto',
            "Confirma tu correo y crea tu contraseña. El enlace caduca en 24 horas.\n\n".$link."\n",
        );
    }

    public function sendPasswordReset(string $email, string $token): void
    {
        $link = $this->urls->generate('password_reset', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->send(
            $email,
            'Crea una contraseña nueva en Aselto',
            "Usa este enlace para crear una contraseña nueva. Caduca en 2 horas.\n\n".$link."\n",
        );
    }

    private function send(string $to, string $subject, string $text): void
    {
        $this->mailer->send(
            (new Email())
                ->from('Aselto <no-responder@aselto.test>')
                ->to($to)
                ->subject($subject)
                ->text($text),
        );
    }
}
