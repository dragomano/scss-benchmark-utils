<?php

declare(strict_types=1);

namespace Bugo\BenchmarkUtils;

final class FileMtimeShim
{
    private static array $overrides = [];

    public static function set(string $path, int|false|null $value): void
    {
        if ($value === null) {
            unset(self::$overrides[$path]);

            return;
        }

        self::$overrides[$path] = $value;
    }

    public static function get(string $path): int|false|null
    {
        return self::$overrides[$path] ?? null;
    }

    public static function reset(): void
    {
        self::$overrides = [];
    }
}

function filemtime(string $filename): int|false
{
    $override = FileMtimeShim::get($filename);

    if ($override !== null) {
        return $override;
    }

    return \filemtime($filename);
}
