<?php

namespace App\Finanzas\Infrastructure\Realtime;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Uid\Uuid;

final class QuarterUpdatePublisher
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly HubInterface $hub, private readonly LoggerInterface $logger)
    {
    }

    public function publish(?string $ownerId = null): int
    {
        try {
            return $this->publishPending($ownerId);
        } catch (\Throwable $error) {
            $this->logger->warning('Quarter notification delivery deferred.', ['exception' => $error]);

            return 0;
        }
    }

    private function publishPending(?string $ownerId): int
    {
        $db = $this->entityManager->getConnection();
        // An enclosing transaction (including rolled-back test fixtures) must never publish.
        if ($db->isTransactionActive()) {
            return 0;
        }
        $rows = $db->fetchAllAssociative('SELECT owner_id, scope, revision FROM quarter_update_outbox WHERE revision > published_revision'.(null === $ownerId ? '' : ' AND owner_id = ?').' LIMIT 100', null === $ownerId ? [] : [$ownerId]);
        $published = 0;
        foreach ($rows as $row) {
            try {
                $recipients = $db->fetchFirstColumn("SELECT id FROM identity_user WHERE id = :owner AND role = 'client' AND activated = 1 UNION SELECT u.id FROM identity_user u JOIN client_profile c ON c.firm_id = u.firm_id WHERE c.user_id = :owner AND u.activated = 1 AND u.role IN ('firm_admin', 'advisor') AND (:scope = 'personal' OR c.firm_id = :scope)", ['owner' => $row['owner_id'], 'scope' => $row['scope']]);
                if ([] !== $recipients) {
                    $this->hub->publish(new Update(array_map(static fn ($id): string => QuarterTopic::forUser((string) $id), $recipients), '{"changed":true}', true, Uuid::v4()->toRfc4122()));
                }
                // A concurrent new revision must remain pending for the next pass.
                $db->executeStatement('UPDATE quarter_update_outbox SET published_revision = GREATEST(published_revision, ?) WHERE owner_id = ? AND scope = ?', [$row['revision'], $row['owner_id'], $row['scope']]);
                ++$published;
            } catch (\Throwable $error) {
                $this->logger->warning('Quarter notification remains pending for retry.', ['exception' => $error]);
            }
        }

        return $published;
    }
}
