<?php

namespace App\Shared\Domain;

final readonly class SecurityEntry
{
    public function __construct(public string $id, public \DateTimeImmutable $at, public SecurityEvent $event, public string $correlation, public ?string $actor = null, public ?string $target = null, public ?string $route = null, public ?string $method = null, public ?string $ipHash = null, public ?string $subjectHash = null)
    {
        foreach ([$id, $actor, $target] as $uuid) {
            if (null !== $uuid && 1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid)) {
                throw new \InvalidArgumentException('Invalid audit identifier.');
            }
        }
        if (1 !== preg_match('/^[a-f0-9]{32}$/', $correlation) || (null !== $route && 1 !== preg_match('/^[a-z_][a-z0-9_]{0,79}$/', $route)) || (null !== $method && !in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS', 'OTHER'], true))) {
            throw new \InvalidArgumentException('Invalid audit context.');
        }
        foreach ([$ipHash, $subjectHash] as $hash) {
            if (null !== $hash && 1 !== preg_match('/^[a-f0-9]{64}$/', $hash)) {
                throw new \InvalidArgumentException('Invalid audit hash.');
            }
        }
    }

    /** @return array<string, string|null> */
    public function data(): array
    {
        return ['id' => $this->id, 'at' => $this->at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'), 'event' => $this->event->value, 'result' => $this->event->result(), 'correlation' => $this->correlation, 'actor' => $this->actor, 'target' => $this->target, 'route' => $this->route, 'method' => $this->method, 'ip_hash' => $this->ipHash, 'subject_hash' => $this->subjectHash];
    }

    /** @param array<string, mixed> $data */
    public static function restore(array $data): self
    {
        $keys = ['id', 'at', 'event', 'result', 'correlation', 'actor', 'target', 'route', 'method', 'ip_hash', 'subject_hash'];
        if (count($data) !== count($keys) || [] !== array_diff(array_keys($data), $keys)) {
            throw new \InvalidArgumentException('Invalid audit schema.');
        }
        foreach ($data as $value) {
            if (null !== $value && !is_string($value)) {
                throw new \InvalidArgumentException('Invalid audit value.');
            }
        }
        foreach (['id', 'at', 'event', 'result', 'correlation'] as $key) {
            if (!is_string($data[$key])) {
                throw new \InvalidArgumentException('Missing audit value.');
            }
        }
        $entry = new self($data['id'], new \DateTimeImmutable($data['at']), SecurityEvent::from($data['event']), $data['correlation'], $data['actor'], $data['target'], $data['route'], $data['method'], $data['ip_hash'], $data['subject_hash']);
        if ($entry->data() !== $data) {
            throw new \InvalidArgumentException('Noncanonical audit entry.');
        }

        return $entry;
    }
}
