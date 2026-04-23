<?php

declare(strict_types=1);

namespace {
    use Bugo\BenchmarkUtils\ReportGenerator;

    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/benchmark_report_test_' . uniqid();

        mkdir($this->tempDir, 0755, true);
    });

    afterEach(function () {
        recursiveDelete($this->tempDir);
    });

    describe('ReportGenerator', function () {
        describe('formatTable()', function () {
            test('formats numeric results correctly', function () {
                $results = [
                    'test/compiler-a' => [
                        'time'   => 0.1234,
                        'size'   => 45.67,
                        'memory' => 1.23,
                    ],
                    'test/compiler-b' => [
                        'time'   => 0.5678,
                        'size'   => 89.01,
                        'memory' => 2.34,
                    ],
                ];

                $table = ReportGenerator::formatTable($results);

                expect($table)->toContain('| Compiler | Time (sec) | CSS Size (KB) | Memory (MB) |')
                    ->toContain('test/compiler-a')
                    ->toContain('test/compiler-b');
            });

            test('formats error results correctly', function () {
                $results = [
                    'test/compiler-error' => [
                        'time'   => 'Error: compilation failed',
                        'size'   => 'N/A',
                        'memory' => 'N/A',
                    ],
                ];

                $table = ReportGenerator::formatTable($results);

                expect($table)->toContain('test/compiler-error')
                    ->toContain('Error: compilation failed')
                    ->toContain('N/A');
            });
        });

        describe('updateMarkdownFile()', function () {
            test('updates OS and PHP version in markdown', function () {
                $markdown = <<<MARKDOWN
    # Benchmark

    ## Test Environment

    - **OS**: Windows 10
    - **PHP version**: 8.0.0
    - **Testing method**: Test

    ## Results

    | Compiler | Time (sec) | CSS Size (KB) | Memory (MB) |
    |------------|-------------|---------------|-------------|
    | old/compiler | 1.0000 | 100.00 | 10.00 |

    *Note: These results are approximate.*
    MARKDOWN;

                $filePath = $this->tempDir . '/benchmark.md';

                file_put_contents($filePath, $markdown);

                $results = [
                    'new/compiler' => [
                        'time'   => 0.5000,
                        'size'   => 50.00,
                        'memory' => 5.00,
                    ],
                ];

                ReportGenerator::updateMarkdownFile($filePath, $results);

                $content = file_get_contents($filePath);

                expect($content)->toContain('new/compiler')
                    ->toContain('0.5000');
            });

            test('does not fail on missing file', function () {
                expect(fn () => ReportGenerator::updateMarkdownFile('/nonexistent/file.md', []))
                    ->not->toThrow(Exception::class);
            });

            test('does not fail when table is missing', function () {
                $markdown = "# Benchmark\n\nNo table here";
                $filePath = $this->tempDir . '/benchmark.md';

                file_put_contents($filePath, $markdown);

                expect(fn () => ReportGenerator::updateMarkdownFile($filePath, []))
                    ->not->toThrow(Exception::class);
            });
        });
    });
}
