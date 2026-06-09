<?php

declare(strict_types=1);

namespace GymTracker;

use PDO;

final class RateLimiter
{
    public static function installSchema(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS rate_limits (
            action VARCHAR(80) NOT NULL,
            identifier VARCHAR(190) NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            window_started_at INTEGER NOT NULL,
            PRIMARY KEY (action, identifier)
        )');
    }

    public static function attempt(PDO $pdo, string $action, string $identifier, int $maxAttempts, int $windowSeconds, ?int $now = null): bool
    {
        $now ??= time();

        $stmt = $pdo->prepare('SELECT attempts, window_started_at FROM rate_limits WHERE action = ? AND identifier = ?');
        $stmt->execute([$action, $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || (int) $row['window_started_at'] <= $now - $windowSeconds) {
            self::upsert($pdo, $action, $identifier, 1, $now);
            return true;
        }

        $attempts = (int) $row['attempts'];
        if ($attempts >= $maxAttempts) {
            return false;
        }

        self::upsert($pdo, $action, $identifier, $attempts + 1, (int) $row['window_started_at']);
        return true;
    }

    private static function upsert(PDO $pdo, string $action, string $identifier, int $attempts, int $windowStartedAt): void
    {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare('INSERT INTO rate_limits (action, identifier, attempts, window_started_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), window_started_at = VALUES(window_started_at)');
        } else {
            $stmt = $pdo->prepare('INSERT INTO rate_limits (action, identifier, attempts, window_started_at) VALUES (?, ?, ?, ?) ON CONFLICT(action, identifier) DO UPDATE SET attempts = excluded.attempts, window_started_at = excluded.window_started_at');
        }
        $stmt->execute([$action, $identifier, $attempts, $windowStartedAt]);
    }
}
