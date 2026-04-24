<?php

declare(strict_types=1);

use Bugo\BenchmarkUtils\BenchmarkRunner;
use Bugo\BenchmarkUtils\CompilationResult;
use Bugo\BenchmarkUtils\CompilerAdapterInterface;
use Bugo\BenchmarkUtils\FileMtimeShim;
use Bugo\BenchmarkUtils\ReportGenerator;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/benchmark_test_' . uniqid();

    mkdir($this->tempDir, 0755, true);

    $this->mockAdapter = new class ('.test { color: blue; }') implements CompilerAdapterInterface {
        public int $warmupCount = 0;

        public function __construct(private readonly string $css) {}

        public function warmup(?string $code, ?string $sourceFile): void
        {
            $this->warmupCount++;
        }

        public function compile(?string $code, ?string $sourceFile, bool $includeSourceMap = true): CompilationResult
        {
            return new CompilationResult($this->css);
        }
    };

    $this->mockAdapterWithSourceMap = new class ('.test { color: orange; }', '{"sources": ["test.scss"]}') implements CompilerAdapterInterface {
        public int $warmupCount = 0;

        public function __construct(private readonly string $css, private readonly string $sourceMap) {}

        public function warmup(?string $code, ?string $sourceFile): void
        {
            $this->warmupCount++;
        }

        public function compile(?string $code, ?string $sourceFile, bool $includeSourceMap = true): CompilationResult
        {
            return new CompilationResult($this->css, $includeSourceMap ? $this->sourceMap : null);
        }
    };

    $this->mockLegacyCompilerWithResultObject = new class ('.test { color: orange; }') {
        public function __construct(private readonly string $css) {}

        public function compileString(string $scss): object
        {
            return new class ($this->css) {
                public function __construct(private readonly string $css) {}

                public function getCss(): string
                {
                    return $this->css;
                }

                public function getSourceMap(): string
                {
                    return '{"sources": ["test.scss"]}';
                }
            };
        }
    };

    $this->mockFailingAdapter = new class implements CompilerAdapterInterface {
        public function warmup(?string $code, ?string $sourceFile): void {}

        public function compile(?string $code, ?string $sourceFile, bool $includeSourceMap = true): CompilationResult
        {
            throw new Exception('Compilation failed');
        }
    };
});

afterEach(function () {
    FileMtimeShim::reset();
    recursiveDelete($this->tempDir);
});

describe('BenchmarkRunner', function () {
    test('addCompiler returns self for fluent interface', function () {
        $runner = new BenchmarkRunner();
        $result = $runner->addCompiler('test-compiler', fn() => new stdClass());

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

    test('setCode returns self for fluent interface', function () {
        $runner = new BenchmarkRunner();
        $result = $runner->setCode('$primary: #fff; .test { color: $primary; }');

        expect($result)->toBe($runner);
    });

    test('setSourceFile returns self for fluent interface', function () {
        $runner = new BenchmarkRunner();
        $result = $runner->setSourceFile($this->tempDir . '/input.scss');

        expect($result)->toBe($runner);
    });
});

describe('formatTable()', function () {
    test('delegates to ReportGenerator', function () {
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

        expect($table)->toBe(ReportGenerator::formatTable($results));
    });
});

describe('updateMarkdownFile()', function () {
    test('remains compatible through BenchmarkRunner', function () {
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
            ->toContain('0.5000')
            ->and($content)->toContain(ReportGenerator::formatTable($results));
    });
});

describe('run()', function () {
    test('executes all added compilers', function () {
        $runner = new BenchmarkRunner();
        $runner->setCode('.test { color: red; }');
        $runner->setRuns(1);
        $runner->setWarmupRuns(0);
        $runner->setOutputDir($this->tempDir);

        $compilerACalled = false;
        $compilerBCalled = false;
        $mockAdapter     = $this->mockAdapter;

        $runner
            ->addCompiler('compiler-a', function () use (&$compilerACalled, $mockAdapter) {
                $compilerACalled = true;

                return $mockAdapter;
            })
            ->addCompiler('compiler-b', function () use (&$compilerBCalled, $mockAdapter) {
                $compilerBCalled = true;

                return $mockAdapter;
            });

        $results = $runner->run();

        expect($compilerACalled)->toBeTrue()
            ->and($compilerBCalled)->toBeTrue()
            ->and($results)->toHaveKey('compiler-a')
            ->and($results)->toHaveKey('compiler-b');
    });

    test('creates result files', function () {
        $runner = new BenchmarkRunner();
        $runner->setCode('.test { color: red; }');
        $runner->setRuns(1);
        $runner->setWarmupRuns(0);
        $runner->setOutputDir($this->tempDir);
        $runner->addCompiler('test-compiler', fn() => $this->mockAdapter);
        $runner->run();

        expect($this->tempDir . DIRECTORY_SEPARATOR . 'result-test-compiler.css')->toBeFile()
            ->and(file_get_contents($this->tempDir . DIRECTORY_SEPARATOR . 'result-test-compiler.css'))
            ->toContain('color: blue');
    });

    test('handles compiler name with slash correctly', function () {
        $runner = new BenchmarkRunner();
        $runner->setCode('.test { color: red; }');
        $runner->setRuns(1);
        $runner->setWarmupRuns(0);
        $runner->setOutputDir($this->tempDir);
        $runner->addCompiler('vendor/package-name', fn() => $this->mockAdapter);
        $runner->run();

        expect($this->tempDir . DIRECTORY_SEPARATOR . 'result-vendor-package-name.css')->toBeFile();
    });

    test('handles compiler exception gracefully', function () {
        $runner = new BenchmarkRunner();
        $runner->setCode('.test { color: red; }');
        $runner->setRuns(1);
        $runner->setWarmupRuns(0);
        $runner->setOutputDir($this->tempDir);
        $runner->addCompiler('failing-compiler', fn() => $this->mockFailingAdapter);

        $results = $runner->run();

        expect($results)->toHaveKey('failing-compiler')
            ->and($results['failing-compiler']['time'])->toContain('Error');
    });

    test('returns error when factory returns unsupported compiler', function () {
        $runner = new BenchmarkRunner();
        $runner->setCode('.test { color: red; }');
        $runner->setRuns(1);
        $runner->setWarmupRuns(0);
        $runner->setOutputDir($this->tempDir);
        $runner->addCompiler('unsupported-compiler', fn() => 'invalid');

        $results = $runner->run();

        expect($results)->toHaveKey('unsupported-compiler')
            ->and($results['unsupported-compiler']['time'])->toContain('Error');
    });

    test('warmup runs through compiler adapter', function () {
        $runner = new BenchmarkRunner();
        $runner->setCode('.test { color: red; }');
        $runner->setRuns(1);
        $runner->setWarmupRuns(2);
        $runner->setOutputDir($this->tempDir);
        $mockAdapter = $this->mockAdapter;

        $runner->addCompiler('warmup-string-test', fn() => $mockAdapter);
        $runner->run();

        expect($mockAdapter->warmupCount)->toBe(2);
    });

    test('adapter can compile source file and save sourcemap', function () {
        $runner     = new BenchmarkRunner();
        $sourceFile = $this->tempDir . '/input.scss';

        file_put_contents($sourceFile, '.test { color: red; }');

        $runner->setSourceFile($sourceFile);
        $runner->setRuns(1);
        $runner->setWarmupRuns(2);
        $runner->setOutputDir($this->tempDir);

        $mockCompiler = new class implements CompilerAdapterInterface {
            public int $warmupCount = 0;

            public array $compileInputs = [];

            public function warmup(?string $code, ?string $sourceFile): void
            {
                $this->warmupCount++;
            }

            public function compile(?string $code, ?string $sourceFile, bool $includeSourceMap = true): CompilationResult
            {
                $this->compileInputs[] = [$code, $sourceFile, $includeSourceMap];

                return new CompilationResult(
                    '.test { color: green; }',
                    $includeSourceMap ? '{"sources": ["input.scss"]}' : null,
                );
            }
        };

        $runner->addCompiler('file-object-compiler', fn() => $mockCompiler);

        $results = $runner->run();

        expect($mockCompiler->warmupCount)->toBe(2)
            ->and($mockCompiler->compileInputs)->toHaveCount(1)
            ->and($results)->toHaveKey('file-object-compiler')
            ->and($this->tempDir . DIRECTORY_SEPARATOR . 'result-file-object-compiler.css')->toBeFile()
            ->and($this->tempDir . DIRECTORY_SEPARATOR . 'result-file-object-compiler.css.map')->toBeFile()
            ->and(file_get_contents($this->tempDir . DIRECTORY_SEPARATOR . 'result-file-object-compiler.css.map'))
            ->toBe('{"sources": ["input.scss"]}');
    });

    test('adapter supports plain css results without sourcemap', function () {
        $runner     = new BenchmarkRunner();
        $sourceFile = $this->tempDir . '/input.scss';

        file_put_contents($sourceFile, '.test { color: red; }');

        $runner
            ->setSourceFile($sourceFile)
            ->setRuns(1)
            ->setWarmupRuns(0)
            ->setOutputDir($this->tempDir)
            ->addCompiler('file-string-compiler', fn() => new class implements CompilerAdapterInterface {
                public function warmup(?string $code, ?string $sourceFile): void {}

                public function compile(?string $code, ?string $sourceFile, bool $includeSourceMap = true): CompilationResult
                {
                    return new CompilationResult('.test { color: black; }');
                }
            });

        $results = $runner->run();

        expect($results)->toHaveKey('file-string-compiler')
            ->and($this->tempDir . DIRECTORY_SEPARATOR . 'result-file-string-compiler.css')->toBeFile()
            ->and($this->tempDir . DIRECTORY_SEPARATOR . 'result-file-string-compiler.css.map')->not->toBeFile()
            ->and(file_get_contents($this->tempDir . DIRECTORY_SEPARATOR . 'result-file-string-compiler.css'))
            ->toContain('color: black');
    });

    test('processTimes trims outliers correctly', function () {
        $runner     = new BenchmarkRunner();
        $reflection = new ReflectionMethod($runner, 'processTimes');

        $times = [0.1, 0.2, 0.3, 0.4, 0.5, 1.0, 2.0];
        $runner->setTrimCount(2);

        $result = $reflection->invoke($runner, $times);

        expect($result)->toBe([0.3, 0.4, 0.5]);
    });

    test('processTimes returns empty array when given empty array', function () {
        $runner     = new BenchmarkRunner();
        $reflection = new ReflectionMethod($runner, 'processTimes');

        $result = $reflection->invoke($runner, []);

        expect($result)->toBe([]);
    });

    test('getCssSize returns null when file does not exist', function () {
        $runner     = new BenchmarkRunner();
        $reflection = new ReflectionMethod($runner, 'getCssSize');

        $runner->setOutputDir('/nonexistent/directory');

        $result = $reflection->invoke($runner, 'nonexistent-compiler');

        expect($result)->toBeNull();
    });

    test('keeps legacy compiler bridge for result objects', function () {
        $runner = new BenchmarkRunner();
        $runner->setCode('.test { color: red; }');
        $runner->setRuns(1);
        $runner->setWarmupRuns(0);
        $runner->setOutputDir($this->tempDir);
        $runner->addCompiler('object-compiler', fn() => $this->mockLegacyCompilerWithResultObject);

        $results = $runner->run();

        expect($results)->toHaveKey('object-compiler')
            ->and($this->tempDir . DIRECTORY_SEPARATOR . 'result-object-compiler.css')->toBeFile();
    });

    test('reuses existing sourcemap when it is newer than source scss', function () {
        $runner    = new BenchmarkRunner();
        $sourceFile = $this->tempDir . '/input.scss';
        $mapFile    = $this->tempDir . DIRECTORY_SEPARATOR . 'result-object-compiler.css.map';

        file_put_contents($sourceFile, '.test { color: red; }');
        file_put_contents($mapFile, '{"sources": ["cached.scss"]}');

        touch($sourceFile, time() - 10);
        touch($mapFile, time());

        $runner
            ->setCode('.test { color: red; }')
            ->setSourceFile($sourceFile)
            ->setRuns(3)
            ->setWarmupRuns(2)
            ->setOutputDir($this->tempDir)
            ->addCompiler('object-compiler', fn() => $this->mockAdapterWithSourceMap);

        $runner->run();

        expect(file_get_contents($mapFile))->toBe('{"sources": ["cached.scss"]}');
    });

    test('regenerates sourcemap when source scss is newer than map', function () {
        $runner     = new BenchmarkRunner();
        $sourceFile = $this->tempDir . '/input.scss';
        $mapFile    = $this->tempDir . DIRECTORY_SEPARATOR . 'result-object-compiler.css.map';

        file_put_contents($sourceFile, '.test { color: red; }');
        file_put_contents($mapFile, '{"sources": ["cached.scss"]}');

        touch($mapFile, time() - 10);
        touch($sourceFile, time());

        $runner
            ->setCode('.test { color: red; }')
            ->setSourceFile($sourceFile)
            ->setRuns(3)
            ->setWarmupRuns(2)
            ->setOutputDir($this->tempDir)
            ->addCompiler('object-compiler', fn() => $this->mockAdapterWithSourceMap);

        $runner->run();

        expect(file_get_contents($mapFile))->toBe('{"sources": ["test.scss"]}');
    });

    test('reuses existing sourcemap when source file is not provided', function () {
        $runner  = new BenchmarkRunner();
        $mapFile = $this->tempDir . DIRECTORY_SEPARATOR . 'result-object-compiler.css.map';

        file_put_contents($mapFile, '{"sources": ["cached.scss"]}');

        $runner
            ->setCode('.test { color: red; }')
            ->setRuns(1)
            ->setWarmupRuns(0)
            ->setOutputDir($this->tempDir)
            ->addCompiler('object-compiler', fn() => $this->mockAdapterWithSourceMap);

        $runner->run();

        expect(file_get_contents($mapFile))->toBe('{"sources": ["cached.scss"]}');
    });

    test('regenerates sourcemap when filemtime cannot be determined', function () {
        $runner     = new BenchmarkRunner();
        $sourceFile = $this->tempDir . '/input.scss';
        $mapFile    = $this->tempDir . DIRECTORY_SEPARATOR . 'result-object-compiler.css.map';

        file_put_contents($sourceFile, '.test { color: red; }');
        file_put_contents($mapFile, '{"sources": ["cached.scss"]}');

        FileMtimeShim::set($mapFile, false);

        $runner
            ->setCode('.test { color: red; }')
            ->setSourceFile($sourceFile)
            ->setRuns(1)
            ->setWarmupRuns(0)
            ->setOutputDir($this->tempDir)
            ->addCompiler('object-compiler', fn() => $this->mockAdapterWithSourceMap);

        $runner->run();

        expect(file_get_contents($mapFile))->toBe('{"sources": ["test.scss"]}');
    });

    test('fluent interface works correctly', function () {
        $runner = new BenchmarkRunner();
        $result = $runner
            ->setRuns(20)
            ->setWarmupRuns(3)
            ->setTrimCount(4)
            ->setOutputDir('/tmp')
            ->setCode('$var: 1;')
            ->setSourceFile('/tmp/input.scss')
            ->addCompiler('test/compiler', fn() => $this->mockAdapter);

        expect($result)->toBeInstanceOf(BenchmarkRunner::class);
    });
});
