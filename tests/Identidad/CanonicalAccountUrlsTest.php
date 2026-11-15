<?php

namespace App\Tests\Identidad;

use App\Identidad\Infrastructure\Mail\CanonicalAccountUrls;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class CanonicalAccountUrlsTest extends TestCase
{
    public function testCanonicalContextIsIsolatedFromRequestAndPreservesPortAndBasePath(): void
    {
        $routes = new RouteCollection();
        $routes->add('reset', new Route('/recuperar/{token}'));
        $context = new RequestContext('/poisoned', 'POST', 'untrusted.example.test', 'http', 9999);
        $generator = new UrlGenerator($routes, $context);
        $urls = new CanonicalAccountUrls($generator, 'https://aselto.example.test:8443/app/');

        self::assertSame('https://aselto.example.test:8443/app/recuperar/a%3Fb%23c', $urls->generate('reset', 'a?b#c'));
        self::assertSame($context, $generator->getContext());
        self::assertSame('untrusted.example.test', $context->getHost());
        $context->setHost('another.example.test');
        $context->setScheme('https');
        self::assertSame('https://aselto.example.test:8443/app/recuperar/token', $urls->generate('reset', 'token'));
    }

    #[DataProvider('invalidOrigins')]
    public function testRejectsUnsafeConfiguration(string $origin): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CanonicalAccountUrls(new UrlGenerator(new RouteCollection(), new RequestContext()), $origin);
    }

    /** @return iterable<array{string}> */
    public static function invalidOrigins(): iterable
    {
        foreach (['', '/relative', '//example.test', 'http://example.test', 'ftp://example.test', 'https://user:pass@example.test', 'https://example.test?x=1', 'https://example.test#fragment', 'https://example.test?', 'https://example.test#', "https://example.test\n", 'https://example.test\\evil', 'https://bad_host.test', 'https://example.test:99999', 'https://example.test:0'] as $origin) {
            yield [$origin];
        }
    }

    #[DataProvider('loopbackOrigins')]
    public function testAllowsLocalHttp(string $origin): void
    {
        $routes = new RouteCollection();
        $routes->add('reset', new Route('/recuperar/{token}'));
        self::assertSame($origin.'/recuperar/token', (new CanonicalAccountUrls(new UrlGenerator($routes, new RequestContext()), $origin))->generate('reset', 'token'));
    }

    /** @return iterable<array{string}> */
    public static function loopbackOrigins(): iterable
    {
        yield ['http://localhost:8080'];
        yield ['http://127.0.0.1:8080'];
        yield ['http://[::1]:8080'];
    }
}
