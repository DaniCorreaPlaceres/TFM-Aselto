<?php

namespace App\Tests\Shared;

use App\Shared\Infrastructure\Http\BrowserSecurityHeaders;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class BrowserSecurityHeadersTest extends TestCase
{
    public function testPdfReviewAloneAllowsWasmWithoutJavascriptEval(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $listener = new BrowserSecurityHeaders();
        foreach (['client_document', 'firm_document', 'client_upload'] as $route) {
            $request = Request::create('/');
            $request->attributes->set('_route', $route);
            $listener->nonce(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
            $response = new Response('', headers: ['Content-Type' => 'text/html']);
            $listener->headers(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
            $policy = $response->headers->get('Content-Security-Policy');
            self::assertSame('client_upload' !== $route, str_contains($policy, "'wasm-unsafe-eval'"));
            self::assertStringNotContainsString("'unsafe-eval'", $policy);
            self::assertStringContainsString("worker-src 'self'", $policy);
        }
    }

    public function testBinaryResponsesKeepTheirPayloadAndPrivateCache(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/documentos/example/archivo');
        $response = new Response('%PDF-fixture', headers: ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
        (new BrowserSecurityHeaders())->headers(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
        self::assertSame('%PDF-fixture', $response->getContent());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        self::assertSame("frame-ancestors 'none'; base-uri 'none'; object-src 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function testSubrequestsDoNotReplaceTheMainPolicy(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $listener = new BrowserSecurityHeaders();
        $request = Request::create('/');
        $listener->nonce(new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST));
        $response = new Response('', headers: ['Content-Type' => 'text/html']);
        $listener->headers(new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response));
        self::assertFalse($request->attributes->has(BrowserSecurityHeaders::NONCE));
        self::assertFalse($response->headers->has('Content-Security-Policy'));
    }
}
