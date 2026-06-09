<?php

declare(strict_types=1);

$sessionPath = dirname(__DIR__) . '/.phpunit-sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0775, true);
}
session_save_path($sessionPath);

require dirname(__DIR__) . '/app/bootstrap.php';
