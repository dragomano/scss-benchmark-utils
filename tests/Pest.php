<?php

declare(strict_types=1);

require_once __DIR__ . '/FileMtimeShim.php';

function recursiveDelete(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $files = array_diff(scandir($dir), ['.', '..']);

    foreach ($files as $file) {
        $path = "$dir/$file";

        is_dir($path) ? recursiveDelete($path) : unlink($path);
    }

    rmdir($dir);
}
