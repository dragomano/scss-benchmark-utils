<?php

declare(strict_types=1);

namespace Bugo\BenchmarkUtils;

class ReportGenerator
{
    public static function formatTable(array $results): string
    {
        $table = "| Compiler | Time (sec) | CSS Size (KB) | Memory (MB) |" . PHP_EOL;
        $table .= "|------------|-------------|---------------|-------------|" . PHP_EOL;

        foreach ($results as $name => $data) {
            $timeStr = is_numeric($data['time']) ? number_format($data['time'], 4) : $data['time'];
            $sizeStr = is_numeric($data['size']) ? number_format($data['size'], 2) : $data['size'];
            $memStr  = is_numeric($data['memory']) ? number_format($data['memory'], 2) : $data['memory'];
            $table .= "| $name | $timeStr | $sizeStr | $memStr |" . PHP_EOL;
        }

        return $table;
    }

    public static function updateMarkdownFile(string $filePath, array $results): void
    {
        if (! file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);
        $content = preg_replace('/- \*\*OS\*\*: .+/', '- **OS**: ' . OsDetector::detect(), $content);
        $content = preg_replace('/- \*\*PHP version\*\*: .+/', '- **PHP version**: ' . PHP_VERSION, $content);

        $tableStart = strpos($content, '| Compiler');

        if ($tableStart === false) {
            return;
        }

        $tableOld = substr($content, $tableStart);
        $newTable = self::formatTable($results);
        $content  = str_replace($tableOld, $newTable, $content);

        file_put_contents($filePath, $content);
    }
}
