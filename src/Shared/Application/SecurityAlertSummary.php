<?php

namespace App\Shared\Application;

use App\Shared\Domain\SecurityEntry;
use App\Shared\Domain\SecurityEvent;

final class SecurityAlertSummary
{
    /** @param iterable<string, SecurityEntry> $entries
     * @return array{cursor: ?string, alerts: array<string, int>}
     */
    public function summarize(iterable $entries): array
    {
        $buckets = [];
        $cursor = null;
        foreach ($entries as $position => $entry) {
            $cursor = $position;
            $category = match ($entry->event) {
                SecurityEvent::LoginFailed, SecurityEvent::AuthenticationRejected => 'authentication_failures',
                SecurityEvent::AuthenticationLimited => 'authentication_limits',
                SecurityEvent::AccessDenied => 'access_denials',
                SecurityEvent::ResourceLimited => 'resource_limits',
                default => null,
            };
            if (null !== $category) {
                $bucket = intdiv($entry->at->getTimestamp(), 900);
                $buckets[$bucket][$category] = ($buckets[$bucket][$category] ?? 0) + 1;
            }
        }
        $thresholds = ['authentication_failures' => 10, 'authentication_limits' => 3, 'access_denials' => 5, 'resource_limits' => 20];
        $alerts = [];
        foreach ($buckets as $counts) {
            foreach ($counts as $category => $count) {
                if ($count >= $thresholds[$category]) {
                    $alerts[$category] = ($alerts[$category] ?? 0) + $count;
                }
            }
        }
        ksort($alerts);

        return ['cursor' => $cursor, 'alerts' => $alerts];
    }
}
