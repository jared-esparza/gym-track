<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    $root . '/app',
    $root . '/public',
    $root . '/tests',
];

foreach ($paths as $path) {
    if (!is_dir($path)) {
        continue;
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $command = PHP_BINARY . ' -l ' . escapeshellarg($file->getPathname());
        passthru($command, $code);
        if ($code !== 0) {
            exit($code);
        }
    }
}
