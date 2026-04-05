<?php

declare(strict_types=1);

namespace Bugo\BenchmarkUtils;

use Throwable;

class BenchmarkRunner
{
    private array $compilers = [];

    private int $runs = 10;

    private int $warmupRuns = 2;

    private int $trimCount = 2;

    private ?string $outputDir = null;

    private ?string $code = null;

    private ?string $sourceFile = null;

    public function addCompiler(string $name, callable $factory): self
    {
        $this->compilers[$name] = $factory;

        return $this;
    }

    public function setRuns(int $runs): self
    {
        $this->runs = $runs;

        return $this;
    }

    public function setWarmupRuns(int $warmupRuns): self
    {
        $this->warmupRuns = $warmupRuns;

        return $this;
    }

    public function setTrimCount(int $trimCount): self
    {
        $this->trimCount = $trimCount;

        return $this;
    }

    public function setOutputDir(?string $dir): self
    {
        $this->outputDir = $dir;

        return $this;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function setSourceFile(?string $file): self
    {
        $this->sourceFile = $file;

        return $this;
    }

    /* @deprecated */
    public function setScssCode(string $scss): self
    {
        return $this->setCode($scss);
    }

    /* @deprecated */
    public function setScssSourceFile(?string $file): self
    {
        return $this->setSourceFile($file);
    }

    public function run(): array
    {
        $results = [];

        foreach ($this->compilers as $name => $factory) {
            $results[$name] = $this->benchmarkCompiler($name, $factory);
        }

        return $results;
    }

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

    private function benchmarkCompiler(string $name, callable $factory): array
    {
        $times               = [];
        $maxMemDelta         = 0;
        $css                 = '';
        $sourceMap           = null;
        $shouldSaveSourceMap = $this->shouldSaveSourceMap($name);

        try {
            $compiler = $factory();

            $this->warmup($compiler);

            for ($i = 0; $i < $this->runs; $i++) {
                $memBefore = memory_get_usage();
                $start     = hrtime(true);

                $result = $this->compile($compiler, $name, $shouldSaveSourceMap && $i === 0);
                $css    = $result['css'] ?? $result;

                $sourceMap ??= $result['sourceMap'] ?? null;

                $times[]     = (hrtime(true) - $start) / 1e9;
                $memAfter    = memory_get_usage();
                $maxMemDelta = max($maxMemDelta, $memAfter - $memBefore);
            }

            $this->saveResults($name, $css, $sourceMap);

            $times   = $this->processTimes($times);
            $cssSize = $this->getCssSize($name);

            return [
                'time'   => $cssSize !== null ? array_sum($times) / count($times) : 'Error',
                'size'   => $cssSize,
                'memory' => $maxMemDelta / 1024 / 1024,
            ];
        } catch (Throwable $e) {
            return [
                'time'   => 'Error: ' . $e->getMessage(),
                'size'   => 'N/A',
                'memory' => 'N/A',
            ];
        }
    }

    private function warmup(object $compiler): void
    {
        $scss = $this->code ?? '';

        for ($i = 0; $i < $this->warmupRuns; $i++) {
            if (method_exists($compiler, 'compileInPersistentMode')) {
                $compiler->compileInPersistentMode($scss);
            } elseif (method_exists($compiler, 'compileString')) {
                $compiler->compileString($scss);
            }
        }
    }

    private function compile(object $compiler, string $name, bool $includeSourceMap = true): array
    {
        $scss = $this->code ?? '';

        if (method_exists($compiler, 'compileInPersistentMode')) {
            return ['css' => $compiler->compileInPersistentMode($scss)];
        }

        if (method_exists($compiler, 'compileString')) {
            $result = $compiler->compileString($scss);

            if (is_object($result) && method_exists($result, 'getCss')) {
                return [
                    'css'       => $result->getCss(),
                    'sourceMap' => $includeSourceMap && method_exists($result, 'getSourceMap') ? $result->getSourceMap() : null,
                ];
            }

            return ['css' => $result];
        }

        throw new UnsupportedCompilerException($name);
    }

    private function shouldSaveSourceMap(string $name): bool
    {
        $mapFile = $this->getMapFile($name);

        if (! file_exists($mapFile)) {
            return true;
        }

        $sourceFile = $this->sourceFile;

        if ($sourceFile === null || ! file_exists($sourceFile)) {
            return false;
        }

        $mapModifiedAt    = filemtime($mapFile);
        $sourceModifiedAt = filemtime($sourceFile);

        if ($mapModifiedAt === false || $sourceModifiedAt === false) {
            return true;
        }

        return $mapModifiedAt < $sourceModifiedAt;
    }

    private function processTimes(array $times): array
    {
        if (count($times) === 0) {
            return $times;
        }

        sort($times);

        $maxTrim = (int) floor((count($times) - 1) / 2);
        $trim    = min($this->trimCount, $maxTrim);

        for ($i = 0; $i < $trim; $i++) {
            array_shift($times);
            array_pop($times);
        }

        return $times;
    }

    private function saveResults(string $name, string $css, ?string $sourceMap): void
    {
        $cssFile = $this->getCssFile($name);

        file_put_contents($cssFile, $css, LOCK_EX);

        if ($sourceMap !== null) {
            $mapFile = $this->getMapFile($name);

            file_put_contents($mapFile, $sourceMap, LOCK_EX);
        }
    }

    private function getCssSize(string $name): ?float
    {
        $cssFile = $this->getCssFile($name);

        if (file_exists($cssFile)) {
            return filesize($cssFile) / 1024;
        }

        return null;
    }

    private function getCssFile(string $name): string
    {
        $outputDir = $this->outputDir ?? __DIR__;
        $package   = str_replace('/', '-', $name);

        return $outputDir . DIRECTORY_SEPARATOR . "result-$package.css";
    }

    private function getMapFile(string $name): string
    {
        return $this->getCssFile($name) . '.map';
    }
}
