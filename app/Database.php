<?php

declare(strict_types=1);

namespace GymTracker;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host = Config::get('DB_HOST', 'localhost');
        $name = Config::get('DB_NAME', 'gym_tracker');
        $charset = Config::get('DB_CHARSET', 'utf8mb4');
        $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";

        self::$pdo = new PDO($dsn, Config::get('DB_USER', ''), Config::get('DB_PASS', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$pdo;
    }
}
