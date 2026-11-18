<?php

namespace App\Shared\Infrastructure\Security;

use App\Shared\Application\SecurityAlertSummary;
use App\Shared\Domain\Clock;
use App\Shared\Domain\SecurityAudit;
use App\Shared\Domain\SecurityEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

final readonly class SecurityAlertDelivery
{
    public function __construct(private PrivateSecurityJournal $journal, private SecurityAlertSummary $summary, private Clock $clock, private LockFactory $locks, private TransportInterface $transport, private SecurityAudit $audit, #[Autowire('%env(bool:SECURITY_ALERT_EMAIL_ENABLED)%')] private bool $enabled, #[Autowire('%env(SECURITY_ALERT_RECIPIENT)%')] private string $recipient, #[Autowire('%env(SECURITY_ALERT_FROM)%')] private string $from)
    {
    }

    public function send(): string
    {
        if (!$this->enabled) {
            return 'disabled';
        }
        $lock = $this->locks->createLock('aselto-security-alerts', null);
        if (!$lock->acquire()) {
            return 'busy';
        }
        try {
            if (!$this->journal->healthy()) {
                throw new \RuntimeException('Security journal unhealthy.');
            }
            $path = $this->journal->directory().'/alerts-state.json';
            if (is_link($path)) {
                throw new \RuntimeException('Invalid alert state.');
            }
            $state = ['attempt' => 0, 'cursor' => null];
            if (is_file($path)) {
                $raw = file_get_contents($path);
                if (false === $raw || strlen($raw) > 1024) {
                    throw new \RuntimeException('Invalid alert state.');
                }
                $state = json_decode($raw, true, 4, JSON_THROW_ON_ERROR);
                if (!is_array($state) || !is_int($state['attempt'] ?? null) || !array_key_exists('cursor', $state) || (null !== $state['cursor'] && !is_string($state['cursor']))) {
                    throw new \RuntimeException('Invalid alert state.');
                }
            }
            $now = $this->clock->now()->getTimestamp();
            if ($now - $state['attempt'] < 3600) {
                return 'waiting';
            }
            $report = $this->summary->summarize($this->journal->scan($state['cursor']));
            if ([] === $report['alerts']) {
                return 'empty';
            }
            $state['attempt'] = $now;
            $this->store($path, $state);
            try {
                $this->transport->send((new Email())->from($this->from)->to($this->recipient)->subject('Aselto: resumen de alertas de seguridad')->text("Eventos que superan los umbrales en ventanas UTC de 15 minutos:\n".json_encode($report['alerts'], JSON_THROW_ON_ERROR)."\nConsultar el registro privado desde operaciones."));
            } catch (\Throwable) {
                $this->audit->record(SecurityEvent::AlertFailed);
                throw new \RuntimeException('Security summary delivery failed.');
            }
            $state['cursor'] = $report['cursor'];
            $this->store($path, $state);
            $this->audit->record(SecurityEvent::AlertSent);

            return 'sent';
        } finally {
            $lock->release();
        }
    }

    /** @param array{attempt: int, cursor: ?string} $state */
    private function store(string $path, array $state): void
    {
        $temporary = tempnam($this->journal->directory(), '.alerts-');
        if (false === $temporary) {
            throw new \RuntimeException('Alert state unavailable.');
        }
        try {
            if (!chmod($temporary, 0600) || false === file_put_contents($temporary, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX) || !rename($temporary, $path)) {
                throw new \RuntimeException('Cannot persist alert state.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
