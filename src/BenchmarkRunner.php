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

    public function run(): array
    {
        $results = [];

        foreach ($this->compilers as $name => $factory) {
            $results[$name] = $this->benchmarkCompiler($name, $factory);
        }

        return $results;
    }

    /** @deprecated Use ReportGenerator::formatTable() instead. */
    public static function formatTable(array $results): string
    {
        return ReportGenerator::formatTable($results);
    }

    /** @deprecated Use ReportGenerator::updateMarkdownFile() instead. */
    public static function updateMarkdownFile(string $filePath, array $results): void
    {
        ReportGenerator::updateMarkdownFile($filePath, $results);
    }

    private function benchmarkCompiler(string $name, callable $factory): array
    {
        $times               = [];
        $maxMemDelta         = 0;
        $css                 = '';
        $sourceMap           = null;
        $shouldSaveSourceMap = $this->shouldSaveSourceMap($name);

        try {
            $compiler = $this->resolveCompilerAdapter($name, $factory());

            $this->warmup($compiler);

            for ($i = 0; $i < $this->runs; $i++) {
                $memBefore = memory_get_usage();
                $start     = hrtime(true);

                $result = $this->compile($compiler, $shouldSaveSourceMap && $i === 0);
                $css    = $result->css;

                $sourceMap ??= $result->sourceMap;

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

    private function warmup(CompilerAdapterInterface $compiler): void
    {
        for ($i = 0; $i < $this->warmupRuns; $i++) {
            $compiler->warmup($this->code, $this->sourceFile);
        }
    }

    private function compile(CompilerAdapterInterface $compiler, bool $includeSourceMap = true): CompilationResult
    {
        return $compiler->compile($this->code, $this->sourceFile, $includeSourceMap);
    }

    private function resolveCompilerAdapter(string $name, mixed $compiler): CompilerAdapterInterface
    {
        if ($compiler instanceof CompilerAdapterInterface) {
            return $compiler;
        }

        if (is_object($compiler)) {
            return new LegacyCompilerAdapter($compiler, $name);
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
