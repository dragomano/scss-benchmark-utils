<?php

declare(strict_types=1);

use Bugo\BenchmarkUtils\BenchmarkRunner;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/benchmark_test_' . uniqid();

    mkdir($this->tempDir, 0755, true);

    $this->mockCompiler = new class ('.test { color: blue; }') {
        private string $css;

        public function __construct(string $css)
        {
            $this->css = $css;
        }

        public function compileString(string $scss): string
        {
            return $this->css;
        }
    };

    $this->mockPersistentCompiler = new class ('.test { color: purple; }') {
        private string $css;

        public function __construct(string $css)
        {
            $this->css = $css;
        }

        public function compileInPersistentMode(string $scss): string
        {
            return $this->css;
        }
    };

    $this->mockCompilerWithResultObject = new class ('.test { color: orange; }') {
        private string $css;

        public function __construct(string $css)
        {
            $this->css = $css;
        }

        public function compileString(string $scss): object
        {
            return new class ($this->css) {
                private string $css;

                public function __construct(string $css)
                {
                    $this->css = $css;
                }

                public function getCss(): string
                {
                    return $this->css;
                }

                public function getSourceMap(): ?string
                {
                    return '{"sources": ["test.scss"]}';
                }
            };
        }
    };

    $this->mockFailingCompiler = new class {
        public function compileString(string $scss): void
        {
            throw new \Exception('Compilation failed');
        }
    };
});

afterEach(function () {
    recursiveDelete($this->tempDir);
});

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

describe('BenchmarkRunner', function () {
    test('addCompiler returns self for fluent interface', function () {
        $runner = new BenchmarkRunner();
        $result = $runner->addCompiler('test-compiler', function () {
            return new \stdClass();
        });

        expect($result)->toBe($runner);
    });

    test('setRuns returns self for fluent interface', function () {
        $runner = new BenchmarkRunner();
        $result = $runner->setRuns(50);

        expect($result)->toBe($runner);
    });

    test('setWarmupRuns returns self for fluent interface', function () {
        $runner = new BenchmarkRunner();
        $result = $runner->setWarmupRuns(5);

        expect($result)->toBe($runner);
    });

    test('setTrimCount returns self for fluent interface', function () {
        $runner = new BenchmarkRunner();
        $result = $runner->setTrimCount(3);

        expect($result)->toBe($runner);
    });

    test('setOutputDir returns self for fluent interface', function () {
        $runner = new BenchmarkRunner();
        $result = $runner->setOutputDir($this->tempDir);

        expect($result)->toBe($runner);
    });

    test('setScssCode returns self for fluent interface', function () {
        $runner = new BenchmarkRunner();
        $result = $runner->setScssCode('$primary: #fff; .test { color: $primary; }');

        expect($result)->toBe($runner);
    });
});

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

        $table = BenchmarkRunner::formatTable($results);

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

        $table = BenchmarkRunner::formatTable($results);

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

        BenchmarkRunner::updateMarkdownFile($filePath, $results);

        $content = file_get_contents($filePath);

        expect($content)->toContain('new/compiler')
            ->toContain('0.5000');
    });

    test('does not fail on missing file', function () {
        expect(fn () => BenchmarkRunner::updateMarkdownFile('/nonexistent/file.md', []))->not->toThrow(\Exception::class);
    });

    test('does not fail when table is missing', function () {
        $markdown = "# Benchmark\n\nNo table here";
        $filePath = $this->tempDir . '/benchmark.md';

        file_put_contents($filePath, $markdown);

        expect(fn () => BenchmarkRunner::updateMarkdownFile($filePath, []))->not->toThrow(\Exception::class);
    });
});

describe('run()', function () {
    test('executes all added compilers', function () {
        $runner = new BenchmarkRunner();
        $runner->setScssCode('.test { color: red; }');
        $runner->setRuns(1);
        $runner->setWarmupRuns(0);
        $runner->setOutputDir($this->tempDir);

        $compilerACalled = false;
        $compilerBCalled = false;
        $mockCompiler    = $this->mockCompiler;

        $runner
            ->addCompiler('compiler-a', function () use (&$compilerACalled, $mockCompiler) {
                $compilerACalled = true;

                return $mockCompiler;
            })
            ->addCompiler('compiler-b', function () use (&$compilerBCalled, $mockCompiler) {
                $compilerBCalled = true;

                return $mockCompiler;
            });

        $results = $runner->run();

        expect($compilerACalled)->toBeTrue()
            ->and($compilerBCalled)->toBeTrue()
            ->and($results)->toHaveKey('compiler-a')
            ->and($results)->toHaveKey('compiler-b');
    });

    test('creates result files', function () {
        $runner = new BenchmarkRunner();
        $runner->setScssCode('.test { color: red; }');
        $runner->setRuns(1);
        $runner->setWarmupRuns(0);
        $runner->setOutputDir($this->tempDir);
        $runner->addCompiler('test-compiler', fn () => $this->mockCompiler);
        $runner->run();

        expect($this->tempDir . DIRECTORY_SEPARATOR . 'result-test-compiler.css')->toBeFile()
            ->and(file_get_contents($this->tempDir . DIRECTORY_SEPARATOR . 'result-test-compiler.css'))->toContain('color: blue');
    });

    test('handles compiler name with slash correctly', function () {
        $runner = new BenchmarkRunner();
        $runner->setScssCode('.test { color: red; }');
        $runner->setRuns(1);
        $runner->setWarmupRuns(0);
        $runner->setOutputDir($this->tempDir);
        $runner->addCompiler('vendor/package-name', fn () => $this->mockCompiler);
        $runner->run();

        expect($this->tempDir . DIRECTORY_SEPARATOR . 'result-vendor-package-name.css')->toBeFile();
    });

    test('works with persistent mode compiler', function () {
        $runner = new BenchmarkRunner();
        $runner->setScssCode('.test { color: red; }');
        $runner->setRuns(1);
        $runner->setWarmupRuns(0);
        $runner->setOutputDir($this->tempDir);
        $runner->addCompiler('persistent-compiler', fn () => $this->mockPersistentCompiler);

        $results = $runner->run();

        expect($results)->toHaveKey('persistent-compiler')
            ->and($results['persistent-compiler']['time'])->toBeNumeric();
    });

    test('handles compiler exception gracefully', function () {
        $runner = new BenchmarkRunner();
        $runner->setScssCode('.test { color: red; }');
        $runner->setRuns(1);
        $runner->setWarmupRuns(0);
        $runner->setOutputDir($this->tempDir);
        $runner->addCompiler('failing-compiler', fn () => $this->mockFailingCompiler);

        $results = $runner->run();

        expect($results)->toHaveKey('failing-compiler')
            ->and($results['failing-compiler']['time'])->toContain('Error');
    });

    test('throws exception when compiler does not support required methods', function () {
        $runner = new BenchmarkRunner();
        $runner->setScssCode('.test { color: red; }');
        $runner->setRuns(1);
        $runner->setWarmupRuns(0);
        $runner->setOutputDir($this->tempDir);
        $runner->addCompiler('unsupported-compiler', fn () => new \stdClass());

        $results = $runner->run();

        expect($results)->toHaveKey('unsupported-compiler')
            ->and($results['unsupported-compiler']['time'])->toContain('Error');
    });

    test('warmup runs compileInPersistentMode when available', function () {
        $runner = new BenchmarkRunner();
        $runner->setScssCode('.test { color: red; }');
        $runner->setRuns(1);
        $runner->setWarmupRuns(2);
        $runner->setOutputDir($this->tempDir);

        $persistentCompiler = new class ('.test { color: purple; }') {
            public int $compileCount = 0;

            private string $css;

            public function __construct(string $css)
            {
                $this->css = $css;
            }

            public function compileInPersistentMode(string $scss): string
            {
                $this->compileCount++;

                return $this->css;
            }
        };

        $runner->addCompiler('warmup-test', fn () => $persistentCompiler);
        $runner->run();

        expect($persistentCompiler->compileCount)->toBe(3);
    });

    test('warmup runs compileString when compileInPersistentMode is not available', function () {
        $runner = new BenchmarkRunner();
        $runner->setScssCode('.test { color: red; }');
        $runner->setRuns(1);
        $runner->setWarmupRuns(2);
        $runner->setOutputDir($this->tempDir);

        $mockCompiler = new class ('.test { color: blue; }') {
            public int $compileCount = 0;

            private string $css;

            public function __construct(string $css)
            {
                $this->css = $css;
            }

            public function compileString(string $scss): string
            {
                $this->compileCount++;

                return $this->css;
            }
        };

        $runner->addCompiler('warmup-string-test', fn () => $mockCompiler);
        $runner->run();

        expect($mockCompiler->compileCount)->toBe(3);
    });

    test('processTimes trims outliers correctly', function () {
        $runner     = new BenchmarkRunner();
        $reflection = new \ReflectionMethod($runner, 'processTimes');
        $reflection->setAccessible(true);

        $times = [0.1, 0.2, 0.3, 0.4, 0.5, 1.0, 2.0];
        $runner->setTrimCount(2);

        $result = $reflection->invoke($runner, $times);

        expect($result)->toBe([0.3, 0.4, 0.5]);
    });

    test('getCssSize returns null when file does not exist', function () {
        $runner     = new BenchmarkRunner();
        $reflection = new \ReflectionMethod($runner, 'getCssSize');
        $reflection->setAccessible(true);

        $runner->setOutputDir('/nonexistent/directory');

        $result = $reflection->invoke($runner, 'nonexistent-compiler');

        expect($result)->toBeNull();
    });

    test('handles compiler with result object', function () {
        $runner = new BenchmarkRunner();
        $runner->setScssCode('.test { color: red; }');
        $runner->setRuns(1);
        $runner->setWarmupRuns(0);
        $runner->setOutputDir($this->tempDir);
        $runner->addCompiler('object-compiler', fn () => $this->mockCompilerWithResultObject);

        $results = $runner->run();

        expect($results)->toHaveKey('object-compiler')
            ->and($this->tempDir . DIRECTORY_SEPARATOR . 'result-object-compiler.css')->toBeFile();
    });

    test('fluent interface works correctly', function () {
        $runner = new BenchmarkRunner();
        $result = $runner
            ->setRuns(20)
            ->setWarmupRuns(3)
            ->setTrimCount(4)
            ->setOutputDir('/tmp')
            ->setScssCode('$var: 1;')
            ->addCompiler('test/compiler', fn () => $this->mockCompiler);

        expect($result)->toBeInstanceOf(BenchmarkRunner::class);
    });
});
