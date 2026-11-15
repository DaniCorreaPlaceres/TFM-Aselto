<?php

namespace App\Shared\Ui;

use App\Shared\Application\BrowseRecords;
use App\Shared\Domain\RecordCriteria;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class RecordTableView
{
    public function __construct(private UrlGeneratorInterface $urls, private Environment $twig, private BrowseRecords $browse)
    {
    }

    /** @return list<list<string>> */
    public function initial(string $actorId, string $kind, string $section = ''): array
    {
        try {
            $page = $this->browse->execute($actorId, $kind, new RecordCriteria(section: $section));
        } catch (\DomainException) {
            throw new AccessDeniedHttpException();
        }

        return array_map(fn (array $row): array => $this->cells($kind, $row), $page->rows);
    }

    /** @return list<string> */
    public static function headers(string $kind): array
    {
        return match ($kind) {
            'documents' => ['Documento', 'Tipo', 'Estado'],
            'inbox' => ['Cliente y documento', 'Estado', 'Total'],
            'clients', 'independent' => ['Nombre', 'Tipo', 'Correo'],
            'firms' => ['Asesoría', 'Correo del admin'],
            'team' => ['Correo', 'Papel'],
            'invitations' => ['Correo', 'Tipo de cuenta', 'Estado', 'Último envío', 'Caducidad'],
            'conversations' => ['Asunto', 'Estado', 'Cliente', 'Nuevos'],
            'movements' => ['Fecha', 'Tipo', 'Tercero', 'Base', 'Impuesto', 'Total'],
            default => throw new \InvalidArgumentException('Unknown listing.'),
        };
    }

    /** @param array<string, mixed> $row
     * @return list<string|float>
     */
    public function values(string $kind, array $row): array
    {
        return match ($kind) {
            'documents' => [(string) $row['name'], (string) $row['direction'], (string) $row['status']],
            'inbox' => [$row['client'].' · '.$row['name'], (string) $row['status'], null === $row['total'] ? '' : (float) ((int) $row['total'] / 100)],
            'clients', 'independent' => [(string) $row['name'], (string) $row['kind'], (string) $row['email']],
            'firms' => [(string) $row['name'], (string) $row['email']],
            'team' => [(string) $row['email'], (string) $row['role']],
            'conversations' => [(string) $row['subject'], (string) $row['status'], (string) $row['client'], $row['unread'].' mensajes nuevos para ti'],
            'movements' => [(string) $row['issued_on'], (string) $row['direction'], (string) $row['name'], (float) ((int) $row['base_cents'] / 100), (string) $row['tax'], (float) ((int) $row['total_cents'] / 100)],
            'invitations' => [(string) $row['email'], match ($row['target']) {
                'advisory' => 'Asesoría (cuenta de admin)', 'company' => 'Empresa sin asesoría', default => 'Autónomo sin asesoría'
            }, (string) $row['status'], $this->date((string) $row['sent_at']), $this->date((string) $row['expires_at'])],
            default => throw new \InvalidArgumentException('Unknown listing.'),
        };
    }

    /** @param array<string, mixed> $row
     * @return list<string>
     */
    public function cells(string $kind, array $row): array
    {
        $cells = array_map(static fn (string|float $value): string => htmlspecialchars(is_float($value) ? number_format($value, 2, ',', '.').' €' : $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $this->values($kind, $row));
        $route = match ($kind) {
            'documents' => 'client_document', 'inbox' => 'firm_document', 'clients' => 'client_fiscal', 'independent' => 'platform_independent_client', 'firms' => 'platform_firm_detail', 'conversations' => 'message_thread', default => null
        };
        if (null !== $route) {
            $cells[0] = '<a href="'.htmlspecialchars($this->urls->generate($route, ['id' => $row['id']]), ENT_QUOTES).'">'.$cells[0].'</a>';
        }
        if ('clients' === $kind) {
            $cells[] = '<a href="'.htmlspecialchars($this->urls->generate('firm_client_quarter', ['id' => $row['id']]), ENT_QUOTES).'">Ver trimestre</a>';
        }
        if ('invitations' === $kind) {
            $cells[] = $this->twig->render('ui/_invitation_action.html.twig', ['row' => $row]);
        }
        if ('movements' === $kind) {
            $cells[0] = (new \DateTimeImmutable((string) $row['issued_on']))->format('d/m/Y');
            $route = $row['owner_view'] ? 'client_document' : ($row['firm_view'] ? 'firm_document' : null);
            $cells[] = null === $route ? 'Histórico personal' : '<a href="'.htmlspecialchars($this->urls->generate($route, ['id' => $row['id']]), ENT_QUOTES).'">Ver registro</a>';
        }

        return $cells;
    }

    private function date(string $value): string
    {
        return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Atlantic/Canary'))->format('d/m/Y H:i');
    }
}
