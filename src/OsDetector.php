<?php

declare(strict_types=1);

namespace Bugo\BenchmarkUtils;

class OsDetector
{
    public static function detect(): string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return self::getWindowsVersion();
        }

        return php_uname('s') . ' ' . php_uname('r');
    }

    private static function getWindowsVersion(): string
    {
        exec('cmd /c ver', $output);
        $verOutput = implode("\n", $output);

        if (! preg_match('/\[Version ([\d.]+)]/', $verOutput, $matches)) {
            return php_uname('s') . ' ' . php_uname('r');
        }

        $build = $matches[1];
        $buildNum = (int) explode('.', $build)[2];

        $os = $buildNum >= 22000 ? 'Windows 11' : 'Windows 10';
        $release = self::getWindowsRelease($buildNum);

        return $os . ' ' . $release . ' (Build ' . $build . ')';
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
            default => 'Unknown',
        };
    }
}
