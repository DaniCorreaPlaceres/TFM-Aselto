<?php

namespace App\Shared\Infrastructure;

use App\Shared\Domain\DocumentQuota;
use App\Shared\Domain\LicensePlan;
use App\Shared\Domain\LicenseRepository;
use App\Shared\Domain\LicenseUnavailable;
use App\Shared\Domain\MonthlyQuota;
use App\Shared\Domain\PlanCatalog;
use Doctrine\DBAL\Connection;

final readonly class DoctrineLicenses implements LicenseRepository, DocumentQuota
{
    public function __construct(private Connection $connection)
    {
    }

    public function quota(string $ownerId, \DateTimeImmutable $at): MonthlyQuota
    {
        [$kind, $id] = $this->subject($ownerId);
        $plan = $this->plan($kind, $id);

        return $this->usage($ownerId, $at, $plan);
    }

    public function consume(string $ownerId, string $documentId, \DateTimeImmutable $at): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new \LogicException('Quota consumption requires the document transaction.');
        }
        // The caller holds the client profile lock, which also protects linking.
        [$kind, $id] = $this->subject($ownerId);
        $this->lock($kind, $id);
        $quota = $this->usage($ownerId, $at, $this->plan($kind, $id, true));
        $quota->assertAvailable();
        $this->connection->insert('document_usage', ['document_id' => $documentId, 'owner_id' => $ownerId, 'month' => $quota->month]);
    }

    public function subjects(): array
    {
        $firms = $this->connection->fetchAllAssociative("SELECT f.id, 'firm' AS kind, f.name, COALESCE(l.plan, 'free') AS plan FROM advisory_firm f LEFT JOIN account_license l ON l.subject_id=f.id AND l.kind='firm' ORDER BY f.name");
        $clients = $this->connection->fetchAllAssociative("SELECT u.id, 'client' AS kind, c.name, COALESCE(l.plan, 'free') AS plan FROM client_profile c JOIN identity_user u ON u.id=c.user_id LEFT JOIN account_license l ON l.subject_id=u.id AND l.kind='client' WHERE c.firm_id IS NULL AND u.firm_id IS NULL AND u.role='client' ORDER BY c.name");

        return array_map(static fn (array $row): array => ['id' => (string) $row['id'], 'kind' => (string) $row['kind'], 'name' => (string) $row['name'], 'plan' => (string) $row['plan']], [...$firms, ...$clients]);
    }

    public function assign(string $kind, string $id, LicensePlan $plan, string $actorId, \DateTimeImmutable $at): void
    {
        $this->connection->transactional(function () use ($kind, $id, $plan, $actorId, $at): void {
            if ('client' === $kind) {
                $profile = $this->connection->fetchAssociative('SELECT firm_id FROM client_profile WHERE user_id=? FOR UPDATE', [$id]);
                $owner = $this->connection->fetchAssociative('SELECT firm_id, role FROM identity_user WHERE id=?', [$id]);
                if (false === $profile || null !== $profile['firm_id'] || false === $owner || null !== $owner['firm_id'] || 'client' !== $owner['role'] || LicensePlan::Advisory === $plan) {
                    throw new LicenseUnavailable('Independent client required.');
                }
            } elseif ('firm' !== $kind || LicensePlan::Professional === $plan || false === $this->connection->fetchOne('SELECT id FROM advisory_firm WHERE id=?', [$id])) {
                throw new LicenseUnavailable('Advisory firm required.');
            }
            $this->lock($kind, $id);
            $history = json_decode((string) $this->connection->fetchOne('SELECT history FROM account_license WHERE subject_id=? FOR UPDATE', [$id]), true, flags: JSON_THROW_ON_ERROR);
            $history[] = ['plan' => $plan->value, 'actor' => $actorId, 'at' => $at->format(DATE_ATOM)];
            $this->connection->update('account_license', ['plan' => $plan->value, 'history' => json_encode($history, JSON_THROW_ON_ERROR)], ['subject_id' => $id]);
        });
    }

    public function firm(string $firmId): array
    {
        if (false === $this->connection->fetchOne('SELECT id FROM advisory_firm WHERE id=?', [$firmId])) {
            throw new LicenseUnavailable('Firm not found.');
        }
        $clients = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM client_profile c JOIN identity_user u ON u.id=c.user_id AND u.firm_id=c.firm_id WHERE c.firm_id=? AND u.role='client' AND u.activated=1", [$firmId]);
        $plan = $this->plan('firm', $firmId);

        return ['plan' => $plan->value, 'clients' => $clients, 'reference_cents' => LicensePlan::Advisory === $plan ? $clients * PlanCatalog::ADVISORY_CLIENT_CENTS : 0];
    }

    /** @return array{string, string} */
    private function subject(string $ownerId): array
    {
        $row = $this->connection->fetchAssociative("SELECT c.firm_id FROM client_profile c JOIN identity_user u ON u.id=c.user_id WHERE c.user_id=? AND u.role='client' AND (c.firm_id=u.firm_id OR (c.firm_id IS NULL AND u.firm_id IS NULL))", [$ownerId]);
        if (false === $row) {
            throw new LicenseUnavailable('Client account not available.');
        }

        return null === $row['firm_id'] ? ['client', $ownerId] : ['firm', (string) $row['firm_id']];
    }

    private function lock(string $kind, string $id): void
    {
        $this->connection->executeStatement("INSERT IGNORE INTO account_license (subject_id, kind, plan, history) VALUES (?, ?, 'free', '[]')", [$id, $kind]);
        $row = $this->connection->fetchAssociative('SELECT kind FROM account_license WHERE subject_id=? FOR UPDATE', [$id]);
        if (false === $row || $kind !== $row['kind']) {
            throw new LicenseUnavailable('License destination mismatch.');
        }
    }

    private function plan(string $kind, string $id, bool $locked = false): LicensePlan
    {
        $value = $this->connection->fetchOne('SELECT plan FROM account_license WHERE subject_id=? AND kind=?'.($locked ? ' FOR UPDATE' : ''), [$id, $kind]);
        $plan = false === $value ? LicensePlan::Free : LicensePlan::from((string) $value);

        return ('firm' === $kind && LicensePlan::Professional === $plan) || ('client' === $kind && LicensePlan::Advisory === $plan) ? LicensePlan::Free : $plan;
    }

    private function usage(string $ownerId, \DateTimeImmutable $at, LicensePlan $plan): MonthlyQuota
    {
        $month = MonthlyQuota::month($at);
        $used = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM document_usage WHERE owner_id=? AND month=?', [$ownerId, $month]);

        return new MonthlyQuota($plan, $month, $used);
    }
}
