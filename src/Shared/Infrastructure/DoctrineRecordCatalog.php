<?php

namespace App\Shared\Infrastructure;

use App\Shared\Domain\RecordCatalog;
use App\Shared\Domain\RecordCriteria;
use App\Shared\Domain\RecordPage;
use Doctrine\DBAL\Connection;

final readonly class DoctrineRecordCatalog implements RecordCatalog
{
    private const CONVERSATIONS = 'client_conversation m JOIN client_profile c ON c.user_id=m.owner_id AND c.firm_id=m.firm_id JOIN identity_user a ON a.id=:scope AND a.firm_id=m.firm_id';
    private const CONVERSATION_ACCESS = "a.activated=1 AND (a.role IN ('firm_admin','advisor') OR (a.role='client' AND m.owner_id=a.id))";
    private const UNREAD = "(SELECT COUNT(*) FROM JSON_TABLE(m.messages, '$[*]' COLUMNS(sequence FOR ORDINALITY, author VARCHAR(36) PATH '$.actor')) AS msg WHERE msg.sequence > COALESCE(JSON_UNQUOTE(JSON_EXTRACT(m.readers, CONCAT('$.\"', :scope, '\"'))),0) AND msg.author <> :scope)";

    public function __construct(private Connection $connection)
    {
    }

    public function page(string $kind, string $scope, RecordCriteria $criteria): RecordPage
    {
        $status = "CASE WHEN d.pending_request IS NOT NULL THEN 'Justificante pendiente' ELSE CASE d.status WHEN 'uploaded' THEN 'Subido' WHEN 'extracting' THEN 'Extrayendo' WHEN 'draft' THEN 'Borrador' WHEN 'submitted' THEN 'Enviado' WHEN 'validated' THEN 'Validado' WHEN 'rejected' THEN 'Rechazado' END END";
        $direction = "CASE d.direction WHEN 'income' THEN 'Ingreso' WHEN 'expense' THEN 'Gasto' ELSE '' END";
        $name = "COALESCE(NULLIF(d.party_name, ''), d.original_name)";
        $clientKind = "CASE c.kind WHEN 'self_employed' THEN 'Autónomo' ELSE 'Sociedad' END";
        $conversationStatus = "CASE WHEN m.request=0 THEN 'Conversación' WHEN m.resolved_at IS NULL THEN 'Solicitud pendiente' ELSE 'Solicitud resuelta' END";
        $invitationStatus = "CASE WHEN i.accepted_at IS NOT NULL THEN 'Aceptada' WHEN EXISTS (SELECT 1 FROM identity_user registered WHERE registered.email=i.email) THEN 'Correo ya registrado' WHEN i.expires_at <= UTC_TIMESTAMP() THEN 'Caducada' ELSE 'Pendiente' END";
        $invitationTarget = "CASE i.target WHEN 'advisory' THEN 'Asesoría (cuenta de admin)' WHEN 'company' THEN 'Empresa sin asesoría' ELSE 'Autónomo sin asesoría' END";
        [$from, $where, $columns, $select, $filter] = match ($kind) {
            'documents', 'pending' => ['client_document d', 'd.client_user_id = :scope'.('pending' === $kind ? " AND (d.status IN ('uploaded','extracting','draft','rejected') OR d.pending_request IS NOT NULL)" : ''), [$name, $direction, $status], 'd.id, '.$name.' AS name, '.$direction.' AS direction, '.$status.' AS status', $status],
            'inbox' => ['client_document d JOIN client_profile c ON c.user_id = d.client_user_id AND c.firm_id = d.firm_id', "d.firm_id = :scope AND d.status IN ('submitted', 'rejected', 'validated')", ["CONCAT(c.name, ' · ', $name)", $status, 'd.total_cents'], 'd.id, c.name AS client, '.$name.' AS name, '.$status.' AS status, d.total_cents AS total', $status],
            'clients', 'independent' => ["client_profile c JOIN identity_user u ON u.id = c.user_id AND u.role='client' AND u.firm_id <=> c.firm_id", 'clients' === $kind ? 'c.firm_id = :scope' : 'c.firm_id IS NULL', ['c.name', $clientKind, 'u.email'], 'c.id, c.name, '.$clientKind.' AS kind, u.email', $clientKind],
            'team' => ['identity_user u', "u.firm_id = :scope AND u.role IN ('advisor','firm_admin')", ['u.email', "CASE u.role WHEN 'advisor' THEN 'Gestor' ELSE 'Admin de la asesoría' END"], "u.id, u.email, CASE u.role WHEN 'advisor' THEN 'Gestor' ELSE 'Admin de la asesoría' END AS role", 'u.role'],
            'firms' => ['advisory_firm f', '1=1', ['f.name', "(SELECT CASE WHEN COUNT(*)=1 THEN MAX(u.email) ELSE 'Sin un admin único' END FROM identity_user u WHERE u.firm_id=f.id AND u.role='firm_admin')"], "f.id, f.name, (SELECT CASE WHEN COUNT(*)=1 THEN MAX(u.email) ELSE 'Sin un admin único' END FROM identity_user u WHERE u.firm_id=f.id AND u.role='firm_admin') AS email", 'f.name'],
            'invitations' => ['platform_invitation i', '1=1', ['i.email', $invitationTarget, $invitationStatus, 'i.sent_at', 'i.expires_at'], 'i.id, i.email, i.target, '.$invitationStatus.' AS status, i.sent_at, i.expires_at', 'i.target'],
            'conversations' => [self::CONVERSATIONS, self::CONVERSATION_ACCESS, ['m.subject', $conversationStatus, 'c.name', self::UNREAD], 'm.id, m.subject, '.$conversationStatus.' AS status, c.name AS client, '.self::UNREAD.' AS unread', $conversationStatus],
            'recipients' => ['client_profile c JOIN identity_user a ON a.id=:scope AND a.firm_id=c.firm_id', "a.activated=1 AND (a.role IN ('firm_admin','advisor') OR (a.role='client' AND c.user_id=a.id))", ['c.name'], 'c.id, c.user_id AS userId, c.user_id AS owner_id, c.name', 'c.kind'],
            'shared_documents' => ['client_document d JOIN client_profile c ON c.user_id=d.client_user_id AND c.firm_id=d.firm_id JOIN identity_user a ON a.id=:scope AND a.firm_id=c.firm_id', "a.activated=1 AND c.user_id=:owner AND d.status IN ('submitted','validated','rejected') AND (a.role IN ('firm_admin','advisor') OR (a.role='client' AND c.user_id=a.id))", [$name], 'd.id, '.$name.' AS name, d.original_name, d.status, d.issued_on', 'd.status'],
            default => throw new \InvalidArgumentException('Unknown listing.'),
        };
        $parameters = str_contains($from.$where, ':scope') ? ['scope' => $scope] : [];
        if ('shared_documents' === $kind) {
            $parameters['owner'] = $criteria->owner;
        }
        if ('inbox' === $kind && '' !== $criteria->section) {
            $where .= ' AND d.status = :section';
            $parameters['section'] = $criteria->section;
        }
        $total = $criteria->count ? (int) $this->connection->fetchOne('SELECT /*+ MAX_EXECUTION_TIME(5000) */ COUNT(*) FROM '.$from.' WHERE '.$where, $parameters) : 0;
        if ('' !== $criteria->search) {
            $searches = [];
            foreach ($columns as $index => $column) {
                if ('inbox' === $kind && 2 === $index) {
                    $column = "CONCAT(FORMAT(d.total_cents/100, 2, 'de_DE'), ' €')";
                }
                $searches[] = "LOWER(COALESCE(CAST($column AS CHAR), '')) LIKE :search$index ESCAPE '!'";
                $parameters['search'.$index] = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($criteria->search)).'%';
            }
            $where .= ' AND ('.implode(' OR ', $searches).')';
        }
        if ('' !== $criteria->filter) {
            $where .= ' AND '.$filter.' = :filter';
            $parameters['filter'] = $criteria->filter;
        }
        $filtered = $criteria->count ? (int) $this->connection->fetchOne('SELECT /*+ MAX_EXECUTION_TIME(5000) */ COUNT(*) FROM '.$from.' WHERE '.$where, $parameters) : 0;
        $column = $columns[$criteria->column] ?? $columns[0];
        $alias = match ($kind) {
            'documents', 'pending', 'inbox', 'shared_documents' => 'd', 'clients', 'independent', 'recipients' => 'c', 'team' => 'u', 'firms' => 'f', 'conversations' => 'm', default => 'i',
        };
        $sql = 'SELECT /*+ MAX_EXECUTION_TIME(5000) */ '.$select.' FROM '.$from.' WHERE '.$where.' ORDER BY '.$column.' '.strtoupper($criteria->order).', '.$alias.'.id ASC LIMIT '.$criteria->limit.' OFFSET '.$criteria->offset;

        return new RecordPage($this->connection->fetchAllAssociative($sql, $parameters), $total, $filtered);
    }

    public function attention(string $actorId): array
    {
        $row = $this->connection->fetchAssociative('SELECT /*+ MAX_EXECUTION_TIME(5000) */ COALESCE(SUM('.self::UNREAD.' > 0),0) AS unread, COALESCE(SUM(m.request=1 AND m.resolved_at IS NULL),0) AS requests FROM '.self::CONVERSATIONS.' WHERE '.self::CONVERSATION_ACCESS, ['scope' => $actorId]);

        return ['unread' => (int) ($row['unread'] ?? 0), 'requests' => (int) ($row['requests'] ?? 0)];
    }

    public function snapshot(\Closure $read): mixed
    {
        if ($this->connection->isTransactionActive()) {
            return $read();
        }
        $previous = $this->connection->getTransactionIsolation();
        $this->connection->setTransactionIsolation(\Doctrine\DBAL\TransactionIsolationLevel::REPEATABLE_READ);
        try {
            return $this->connection->transactional(static fn () => $read());
        } finally {
            $this->connection->setTransactionIsolation($previous);
        }
    }
}
