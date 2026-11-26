<?php

namespace App\Tests\Shared;

use App\Shared\Ui\RecordTableInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class ServerTableContextTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function navigationSections(): iterable
    {
        yield 'messages' => ['mensajes'];
        yield 'documents' => ['documentos'];
        yield 'movements' => ['movimientos'];
    }

    #[DataProvider('navigationSections')]
    public function testNavigationSectionDoesNotBecomeAListingFilter(string $section): void
    {
        $html = $this->render(['section' => $section]);
        foreach (['url', 'export'] as $attribute) {
            $url = $this->url($html, $attribute);
            $criteria = (new RecordTableInput())->read(Request::create($url));
            self::assertSame('', $criteria->section);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function inboxSections(): iterable
    {
        foreach (['submitted', 'rejected', 'validated', 'personal'] as $section) {
            yield $section => [$section];
        }
    }

    #[DataProvider('inboxSections')]
    public function testExplicitInboxSectionRemainsInListingAndExport(string $section): void
    {
        $html = $this->render(['kind' => 'inbox', 'section' => 'mensajes', 'tableSection' => $section]);
        foreach (['url', 'export'] as $attribute) {
            $criteria = (new RecordTableInput())->read(Request::create($this->url($html, $attribute)));
            self::assertSame($section, $criteria->section);
        }
    }

    /** @param array<string, mixed> $context */
    private function render(array $context): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates'), ['strict_variables' => true]);
        $twig->addFunction(new TwigFunction('path', static function (string $route, array $parameters): string {
            $kind = $parameters['kind'];
            unset($parameters['kind']);

            return '/listados/'.$kind.('record_table_export' === $route ? '/export' : '').'?'.http_build_query($parameters);
        }));

        return $twig->render('ui/_server_table.html.twig', array_replace(['kind' => 'conversations', 'caption' => 'Prueba', 'headers' => ['Asunto'], 'cells' => [], 'filters' => []], $context));
    }

    private function url(string $html, string $attribute): string
    {
        preg_match('/data-server-table-'.$attribute.'-value="([^"]+)"/', $html, $match);
        self::assertNotEmpty($match[1] ?? null);

        return html_entity_decode($match[1], ENT_QUOTES);
    }
}
