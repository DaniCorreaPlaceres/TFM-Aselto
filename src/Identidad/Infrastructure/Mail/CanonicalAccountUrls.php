<?php

namespace App\Identidad\Infrastructure\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

final class CanonicalAccountUrls
{
    private readonly UrlGeneratorInterface $urls;

    public function __construct(UrlGeneratorInterface $urls, #[Autowire('%env(DEFAULT_URI)%')] string $origin)
    {
        $parts = parse_url($origin);
        if (false === $parts || preg_match('/[\x00-\x20\x7f\\\\]/', $origin)
            || !isset($parts['scheme'], $parts['host'])
            || !in_array($parts['scheme'], ['https', 'http'], true)
            || (isset($parts['port']) && $parts['port'] < 1)
            || array_intersect(['user', 'pass', 'query', 'fragment'], array_keys($parts))
            || ('http' === $parts['scheme'] && !in_array($parts['host'], ['localhost', '127.0.0.1', '[::1]'], true))
            || (false === filter_var($parts['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) && '[::1]' !== $parts['host'])) {
            throw new \InvalidArgumentException('DEFAULT_URI must be an HTTPS URL without credentials, query or fragment; HTTP is allowed only on loopback.');
        }

        // The injected compiled generator is shared with the router: isolate its context.
        $this->urls = clone $urls;
        $this->urls->setContext(RequestContext::fromUri(rtrim($origin, '/')));
    }

    public function generate(string $route, string $token): string
    {
        return $this->urls->generate($route, ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
