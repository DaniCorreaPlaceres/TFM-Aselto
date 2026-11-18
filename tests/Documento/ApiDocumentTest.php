<?php

namespace App\Tests\Documento;

use App\Documento\Domain\DocumentChanged;
use App\Documento\Domain\IncompleteDocument;
use App\Documento\Ui\ApiDocumentExceptionListener;
use App\Documento\Ui\ApiDocumentInput;
use App\Shared\Domain\ClosedPeriod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ApiDocumentTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function invalidBodies(): iterable
    {
        yield 'invalid JSON' => ['{'];
        yield 'array' => ['[]'];
        yield 'missing version' => ['{}'];
        yield 'string version' => ['{"version":"1"}'];
        yield 'float version' => ['{"version":1.0}'];
        yield 'zero version' => ['{"version":0}'];
        yield 'numeric amount' => ['{"version":1,"base":100.50}'];
        yield 'unknown owner' => ['{"version":1,"owner_id":"other"}'];
        yield 'calculated total' => ['{"version":1,"total_cents":1}'];
        yield 'chosen tax' => ['{"version":1,"tax":"ipsi"}'];
    }

    #[DataProvider('invalidBodies')]
    public function testMalformedOrUntrustedInputsAreRejected(string $body): void
    {
        $request = Request::create('/api/v1/documents/id/draft', 'PUT', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
        $this->expectException(BadRequestHttpException::class);
        (new ApiDocumentInput())->json($request, ApiDocumentInput::FIELDS);
    }

    public function testPartialDraftMapsNullToTheExistingUseCaseInput(): void
    {
        $request = Request::create('/api/v1/documents/id/draft', 'PUT', server: ['CONTENT_TYPE' => 'application/json'], content: '{"version":1,"direction":"expense","base":"0.00","tax_rate":null}');
        $input = new ApiDocumentInput();
        self::assertSame(['gasto', '', '', '', '0.00', '', '', ''], $input->arguments($input->json($request, ApiDocumentInput::FIELDS)));
    }

    public function testDecimalCommaDoesNotSilentlyChangeTheAmount(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        (new ApiDocumentInput())->arguments(['base' => '1,000']);
    }

    public function testMissingFieldsUseApiNamesAndKeepTheErrorEnvelope(): void
    {
        $event = $this->event(new IncompleteDocument(['issuedOn', 'taxRate', 'partyName']));
        (new ApiDocumentExceptionListener())($event);
        self::assertSame(422, $event->getResponse()->getStatusCode());
        $data = json_decode($event->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['issued_on', 'tax_rate', 'party_name'], $data['error']['fields']);
        self::assertSame('incomplete_document', $data['error']['code']);
    }

    public function testStaleVersionAndClosedQuarterAreConflicts(): void
    {
        foreach ([new DocumentChanged('internal detail'), new ClosedPeriod('internal detail')] as $error) {
            $event = $this->event($error);
            (new ApiDocumentExceptionListener())($event);
            self::assertSame(409, $event->getResponse()->getStatusCode());
            self::assertStringNotContainsString('internal detail', $event->getResponse()->getContent());
        }
    }

    public function testDocumentErrorsDoNotAlterTheWeb(): void
    {
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create('/documentos/id'), HttpKernelInterface::MAIN_REQUEST, new DocumentChanged());
        (new ApiDocumentExceptionListener())($event);
        self::assertNull($event->getResponse());
    }

    private function event(\Throwable $error): ExceptionEvent
    {
        return new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create('/api/v1/documents/id'), HttpKernelInterface::MAIN_REQUEST, $error);
    }
}
