<?php

namespace App\Shared\Ui;

use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Shared\Application\BrowseRecords;
use App\Shared\Domain\RecordCriteria;
use App\Shared\Infrastructure\RecordExport;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Routing\Attribute\Route;

final class RecordTableController extends AbstractController
{
    #[Route('/opciones/{kind}', name: 'record_options', methods: ['GET'], requirements: ['kind' => 'recipients|shared_documents'])]
    public function options(string $kind, Request $request, RecordTableInput $input, BrowseRecords $browse): Response
    {
        $actor = $this->getUser();
        if (!$actor instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }
        try {
            $page = $browse->execute($actor->id(), $kind, $input->read($request));
        } catch (\DomainException) {
            throw $this->createAccessDeniedException();
        }

        return $this->json(['data' => array_map(static fn (array $row): array => ['value' => $row['owner_id'] ?? $row['id'], 'label' => $row['name']], $page->rows), 'total' => $page->filtered], headers: ['Cache-Control' => 'private, no-store']);
    }

    public function __construct(private readonly \App\Finanzas\Application\BrowseQuarterRecords $quarters)
    {
    }

    private function pageFor(string $actorId, string $kind, RecordCriteria $criteria, BrowseRecords $browse, Request $request): \App\Shared\Domain\RecordPage
    {
        if ('movements' !== $kind) {
            return $browse->execute($actorId, $kind, $criteria);
        }
        $current = $this->quarters->currentPeriod();
        $input = new RecordTableInput();
        try {
            $period = new \App\Finanzas\Domain\QuarterPeriod($input->number($request, 'year', $current->year), $input->number($request, 'quarter', $current->number));

            return $this->quarters->execute($actorId, $request->query->getString('client') ?: null, $period, $request->query->getString('scope') ?: null, $criteria);
        } catch (\App\Finanzas\Domain\QuarterNotFound|\App\Finanzas\Domain\InvalidQuarter) {
            throw $this->createNotFoundException();
        }
    }

    #[Route('/listados/{kind}/exportar', name: 'record_table_export', methods: ['GET'], requirements: ['kind' => 'documents|inbox|clients|independent|firms|team|invitations|conversations|movements'])]
    public function export(string $kind, Request $request, RecordTableInput $input, BrowseRecords $browse, RecordTableView $view, RecordExport $export, LockFactory $locks): Response
    {
        $actor = $this->getUser();
        if (!$actor instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }
        $criteria = $input->read($request);
        $format = $request->query->getString('format', 'csv');
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            throw $this->createNotFoundException();
        }
        try {
            $page = $this->pageFor($actor->id(), $kind, $criteria, $browse, $request);
        } catch (\DomainException) {
            throw $this->createAccessDeniedException();
        }
        if ($page->filtered > 100_000) {
            throw new HttpException(413, 'La exportación supera 100.000 filas. Acota la búsqueda.');
        }
        $lock = $locks->createLock('record-export-'.($actor->firmId() ?? $actor->id()), 60);
        if (!$lock->acquire()) {
            throw new HttpException(429, 'Ya hay una exportación en curso. Inténtalo de nuevo en unos segundos.', headers: ['Retry-After' => '5']);
        }
        try {
            $rows = function () use ($actor, $kind, $criteria, $browse, $view, $request): \Generator {
                $page = $this->pageFor($actor->id(), $kind, $criteria, $browse, $request);
                if ($page->filtered > 100_000) {
                    throw new HttpException(413, 'La exportación supera 100.000 filas. Acota la búsqueda.');
                }
                for ($offset = 0; $offset < $page->filtered; $offset += 100) {
                    $batch = $this->pageFor($actor->id(), $kind, new RecordCriteria($offset, 100, $criteria->search, $criteria->column, $criteria->order, $criteria->filter, $criteria->section, count: false), $browse, $request);
                    foreach ($batch->rows as $row) {
                        yield $view->values($kind, $row);
                    }
                }
            };
            $path = $browse->snapshot(fn (): string => $export->write($format, RecordTableView::headers($kind), $rows()));
        } finally {
            $lock->release();
        }

        return (new BinaryFileResponse($path, headers: ['Cache-Control' => 'private, no-store', 'Content-Type' => 'xlsx' === $format ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8']))->setContentDisposition('attachment', 'Aselto.'.$format)->deleteFileAfterSend();
    }

    #[Route('/listados/{kind}', name: 'record_table', methods: ['GET'], requirements: ['kind' => 'documents|inbox|clients|independent|firms|team|invitations|conversations|movements'])]
    public function page(string $kind, Request $request, RecordTableInput $input, BrowseRecords $browse, RecordTableView $view): Response
    {
        $actor = $this->getUser();
        if (!$actor instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }
        try {
            $page = $this->pageFor($actor->id(), $kind, $input->read($request), $browse, $request);
        } catch (\DomainException) {
            throw $this->createAccessDeniedException();
        }

        return $this->json(['draw' => $input->number($request, 'draw', 0), 'recordsTotal' => $page->total, 'recordsFiltered' => $page->filtered, 'data' => array_map(fn (array $row): array => $view->cells($kind, $row), $page->rows)], headers: ['Cache-Control' => 'private, no-store']);
    }
}
