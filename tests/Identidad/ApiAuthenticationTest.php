<?php

namespace App\Tests\Identidad;

use App\Identidad\Infrastructure\Security\ApiLoginRequest;
use App\Shared\Ui\ApiError;
use App\Shared\Ui\ApiExceptionListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ApiAuthenticationTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function invalidBodies(): iterable
    {
        yield 'empty' => [''];
        yield 'malformed' => ['{'];
        yield 'array' => ['[]'];
        yield 'null' => ['null'];
        yield 'missing password' => ['{"email":"client@example.test"}'];
        yield 'wrong password type' => ['{"email":"client@example.test","password":12}'];
        yield 'wrong email type' => ['{"email":[],"password":"test"}'];
        yield 'invalid email' => ['{"email":"not-email","password":"test"}'];
        yield 'empty password' => ['{"email":"client@example.test","password":""}'];
        yield 'unknown field' => ['{"email":"client@example.test","password":"test","role":"superadmin"}'];
    }

    #[DataProvider('invalidBodies')]
    public function testInvalidLoginRequestsAreRejectedBeforeAuthentication(string $body): void
    {
        $request = Request::create('/api/v1/auth/login', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $this->expectException(BadRequestHttpException::class);
        (new ApiLoginRequest())($event);
    }

    public function testValidationPreservesPasswordWhitespaceAndAllowsNormalizedEmail(): void
    {
        $body = '{"email":" CLIENT@example.test ","password":" pass phrase "}';
        $request = Request::create('/api/v1/auth/login', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
        (new ApiLoginRequest())(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
        self::assertSame($body, $request->getContent());
    }

    public function testInternalApiErrorsNeverExposeExceptionDetails(): void
    {
        $request = Request::create('/api/v1/me');
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new \RuntimeException('sensitive internal details'));
        (new ApiExceptionListener())($event);
        self::assertSame(500, $event->getResponse()->getStatusCode());
        self::assertStringNotContainsString('sensitive internal details', $event->getResponse()->getContent());
        self::assertStringContainsString('internal_error', $event->getResponse()->getContent());
        self::assertStringContainsString('no-store', $event->getResponse()->headers->get('Cache-Control'));
    }

    public function testApiErrorHandlingDoesNotChangeWebErrors(): void
    {
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create('/inicio'), HttpKernelInterface::MAIN_REQUEST, new HttpException(403));
        (new ApiExceptionListener())($event);
        self::assertNull($event->getResponse());
    }

    public function testHttpHeadersArePreservedForMethodErrors(): void
    {
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create('/api/v1/me', 'POST'), HttpKernelInterface::MAIN_REQUEST, new HttpException(405, headers: ['Allow' => 'GET']));
        (new ApiExceptionListener())($event);
        self::assertSame('GET', $event->getResponse()->headers->get('Allow'));
        self::assertSame(405, $event->getResponse()->getStatusCode());
    }

    public function testErrorEnvelopeHasOnlyCodeAndSpanishMessage(): void
    {
        $response = ApiError::response(401, 'token_missing', 'Debes entrar.', ['WWW-Authenticate' => 'Bearer']);
        self::assertSame(['error' => ['code' => 'token_missing', 'message' => 'Debes entrar.']], json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR));
        self::assertSame('Bearer', $response->headers->get('WWW-Authenticate'));
    }
}
