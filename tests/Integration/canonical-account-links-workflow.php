<?php

// Explicit local check. Captures synthetic messages in memory; no SMTP or database writes.
require dirname(__DIR__).'/bootstrap.php';

use App\Identidad\Domain\InvitationTarget;
use App\Identidad\Infrastructure\Mail\CanonicalAccountUrls;
use App\Identidad\Infrastructure\Mail\SymfonyAccountNotifier;
use App\Identidad\Infrastructure\Mail\SymfonyEmailChangeNotifier;
use App\Identidad\Infrastructure\Mail\SymfonyInvitationNotifier;
use App\Kernel;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Routing\RequestContext;

final class CanonicalAccountLinksCheckKernel extends Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->getDefinition(CanonicalAccountUrls::class)->setPublic(true);
            }
        });
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/canonical-account-links-check';
    }
}

$kernel = new CanonicalAccountLinksCheckKernel('dev', true);
$kernel->boot();
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
};
try {
    $router = $kernel->getContainer()->get('router');
    $original = $router->getContext();
    $hostile = new RequestContext('/poisoned', 'POST', 'untrusted.example.test', 'http', 9999);
    $router->setContext($hostile);
    try {
        $urls = $kernel->getContainer()->get(CanonicalAccountUrls::class);
        $mail = new class implements MailerInterface {
            /** @var list<Email> */
            public array $messages = [];

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                if (!$message instanceof Email) {
                    throw new RuntimeException('Expected a synthetic email.');
                }
                $this->messages[] = $message;
            }
        };
        $token = str_repeat('a', 64);
        (new SymfonyAccountNotifier($mail, $urls))->sendActivation('synthetic@example.test', $token);
        (new SymfonyAccountNotifier($mail, $urls))->sendPasswordReset('synthetic@example.test', $token);
        (new SymfonyInvitationNotifier($mail, $urls))->send('synthetic@example.test', InvitationTarget::Advisory, $token);
        (new SymfonyEmailChangeNotifier($mail, $urls))->request('synthetic@example.test', $token);
        $check(4 === count($mail->messages), 'Four sensitive mail types must be checked.');
        $paths = ['/activar/', '/recuperar/', '/invitacion/', '/confirmar-correo-admin/'];
        $origin = rtrim((string) ($_ENV['DEFAULT_URI'] ?? $_SERVER['DEFAULT_URI'] ?? ''), '/');
        $check('' !== $origin, 'Configure the canonical origin before running this workflow.');
        foreach ($mail->messages as $index => $message) {
            $body = $message->getTextBody() ?? '';
            $check(str_contains($body, $origin.$paths[$index].$token), 'Sensitive mail must use the exact canonical origin and route.');
            $check(!str_contains($body, 'untrusted.example.test') && !str_contains($body, '/poisoned'), 'Mail must ignore the HTTP request context.');
        }
        $check($hostile === $router->getContext() && 'untrusted.example.test' === $hostile->getHost(), 'Generation must not mutate the shared router context.');
    } finally {
        $router->setContext($original);
    }
    foreach (['http://localhost/', 'http://127.0.0.1/'] as $url) {
        $request = Request::create($url);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $check(200 === $response->getStatusCode(), 'Allowed local hosts must still work.');
    }
    foreach (['http://untrusted.example.test/recuperar', 'http://localhost.untrusted.example.test/', 'http://untrusted.example.test/api/v1/me'] as $url) {
        $request = Request::create($url);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $check(400 === $response->getStatusCode(), 'Untrusted hosts must be rejected on web and API.');
    }
    $request = Request::create('http://localhost/', server: ['HTTP_X_FORWARDED_HOST' => 'untrusted.example.test', 'HTTP_X_FORWARDED_PROTO' => 'https']);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    $check(200 === $response->getStatusCode() && 'localhost' === $request->getHost() && 'http' === $request->getScheme(), 'Forwarded headers must not be trusted from arbitrary clients.');
    echo 'Canonical account link integration checks: '.$checks." passed.\nNo SMTP, AI calls or database writes.\n";
} finally {
    $kernel->shutdown();
}
