<?php

namespace App\Tests\Cliente;

use App\Cliente\Infrastructure\SymfonyFirmLinkNotifier;
use App\Identidad\Infrastructure\Mail\CanonicalAccountUrls;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class FirmLinkNotifierTest extends TestCase
{
    public function testMessageUsesConfiguredSenderCanonicalOriginAndExplainsConsent(): void
    {
        $routes = new RouteCollection();
        $routes->add('firm_link_accept', new Route('/vincular-asesoria/{token}'));
        $urls = new CanonicalAccountUrls(new UrlGenerator($routes, new RequestContext('', 'GET', 'untrusted.example.test')), 'https://aselto.example.test');
        $token = str_repeat('a', 64);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(static function (Email $email) use ($token): bool {
            self::assertSame('sender@example.test', $email->getFrom()[0]->getAddress());
            self::assertSame('recipient@example.test', $email->getTo()[0]->getAddress());
            self::assertStringContainsString('https://aselto.example.test/vincular-asesoria/'.$token, $email->getTextBody());
            self::assertStringNotContainsString('untrusted.example.test', $email->getTextBody());
            self::assertStringContainsString('24 horas', $email->getTextBody());
            self::assertStringContainsString('compartir tus documentos e histórico', $email->getTextBody());

            return true;
        }));
        (new SymfonyFirmLinkNotifier($mailer, $urls, 'Aselto <sender@example.test>'))->send('recipient@example.test', 'Asesoría ficticia', $token);
    }
}
