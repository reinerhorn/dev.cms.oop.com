<?php

declare(strict_types=1);

/**
 * CMS Cleanup Script
 * Fixes HandlerInterface naming inconsistencies
 */

$projectRoot = realpath(__DIR__ . '/..');

$oldName = 'HandlerInterFace';
$newName = 'HandlerInterface';

$oldNamespace = 'CMS\\Application\\Interface';
$newNamespace = 'CMS\\Application\\Contracts';

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot)
);

$changedFiles = 0;
$totalReplacements = 0;

foreach ($files as $file) {

    if (!$file->isFile()) {
        continue;
    }

    $path = $file->getPathname();

    // nur PHP Dateien
    if (!str_ends_with($path, '.php')) {
        continue;
    }

    $content = file_get_contents($path);

    $original = $content;

    // 1. Interface Name fix
    $content = str_replace($oldName, $newName, $content);

    // 2. Namespace fix (optional, aber empfohlen)
    $content = str_replace($oldNamespace, $newNamespace, $content);

    if ($content !== $original) {

        file_put_contents($path, $content);

        $changedFiles++;

        echo "FIXED: $path\n";
    }
}

echo "\n====================\n";
echo "DONE\n";
echo "Changed files: $changedFiles\n";
