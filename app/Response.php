<?php

declare(strict_types=1);

namespace GymTracker;

final class Response
{
    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        Security::securityHeaders();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, int $status = 400, array $extra = []): never
    {
        self::json(['ok' => false, 'error' => $message] + $extra, $status);
    }
}
