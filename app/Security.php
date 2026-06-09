<?php

declare(strict_types=1);

namespace GymTracker;

final class Security
{
    public static function csrfToken(array &$session): string
    {
        if (empty($session['csrf_token']) || !is_string($session['csrf_token'])) {
            $session['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $session['csrf_token'];
    }

    public static function validCsrfToken(array $session, string $token): bool
    {
        return isset($session['csrf_token'])
            && is_string($session['csrf_token'])
            && $token !== ''
            && hash_equals($session['csrf_token'], $token);
    }

    public static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self'; img-src 'self' data:; connect-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
    }

    public static function csrfHeader(): string
    {
        return (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    }

    public static function isMutatingMethod(string $method): bool
    {
        return in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }
}
