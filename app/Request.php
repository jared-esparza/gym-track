<?php

declare(strict_types=1);

namespace GymTracker;

final class Request
{
    public static function json(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            Response::error('JSON inválido', 400);
        }

        return $data;
    }

    public static function input(): array
    {
        if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            return self::json();
        }

        return $_POST;
    }
}
