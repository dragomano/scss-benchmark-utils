<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withParallel(360)
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withPreparedSets(deadCode: true)
    ->withPhpSets();
