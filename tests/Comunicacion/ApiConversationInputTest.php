<?php

namespace App\Tests\Comunicacion;

use App\Comunicacion\Ui\ApiConversationInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ApiConversationInputTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function invalidBodies(): iterable
    {
        yield 'malformed JSON' => ['{'];
        yield 'array' => ['[]'];
        yield 'null' => ['null'];
        yield 'missing text' => ['{}'];
        yield 'numeric text' => ['{"text":12}'];
        yield 'nested text' => ['{"text":{"text":"hola"}}'];
        yield 'injected author' => ['{"text":"hola","actor_id":"otro"}'];
        yield 'injected files' => ['{"text":"hola","attachments":["ruta"]}'];
    }

    #[DataProvider('invalidBodies')]
    public function testInvalidReplyNeverReachesApplication(string $body): void
    {
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
        $this->expectException(BadRequestHttpException::class);
        new ApiConversationInput()->data($request, false);
    }

    public function testResolveRequiresEmptyObject(): void
    {
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertSame([], new ApiConversationInput()->data($request, false, true));
    }

    public function testOversizedJsonIsRejected(): void
    {
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: str_repeat(' ', 32_769));
        try {
            new ApiConversationInput()->data($request, false);
            self::fail('Expected payload rejection.');
        } catch (HttpException $error) {
            self::assertSame(413, $error->getStatusCode());
        }
    }
}
