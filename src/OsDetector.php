<?php

declare(strict_types=1);

namespace Bugo\BenchmarkUtils;

class OsDetector
{
    public static function detect(string $osFamily = PHP_OS_FAMILY): string
    {
        if ($osFamily === 'Windows') {
            return self::getWindowsVersion();
        }

        return self::defaultVersion();
    }

    private static function defaultVersion(): string
    {
        return php_uname('s') . ' ' . php_uname('r');
    }

    private static function getWindowsVersion(): string
    {
        exec('cmd /c ver', $output);

        return self::parseWindowsVersion(implode("\n", $output));
    }

    private static function parseWindowsVersion(string $verOutput): string
    {
        if (! preg_match('/\[Version ([\d.]+)]/', $verOutput, $matches)) {
            return self::defaultVersion();
        }

        $build    = $matches[1];
        $parts    = explode('.', $build);
        $buildNum = isset($parts[2]) ? (int) $parts[2] : 0;

        $os      = self::getWindowsName($buildNum);
        $release = self::getWindowsRelease($buildNum);

        return $os . ' ' . $release . ' (Build ' . $build . ')';
    }

    private static function getWindowsName(int $buildNum): string
    {
        return $buildNum >= 22000 ? 'Windows 11' : 'Windows 10';
    }

    private static function getWindowsRelease(int $buildNum): string
    {
        return match (true) {
            $buildNum >= 28000 => '26H1',
            $buildNum >= 26200 => '25H2',
            $buildNum >= 26100 => '24H2',
            $buildNum >= 22631 => '23H2',
            $buildNum >= 22621 => '22H2',
            $buildNum >= 22000 => '21H2',
            default            => 'Unknown',
        };
    }
}
