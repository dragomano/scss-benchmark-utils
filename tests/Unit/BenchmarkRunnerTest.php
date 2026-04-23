<?php

declare(strict_types=1);

namespace Bugo\BenchmarkUtils {
    final class BenchmarkRunnerFileMtimeShim
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
        $override = BenchmarkRunnerFileMtimeShim::get($filename);

        if ($override !== null) {
            return $override;
        }

        return \filemtime($filename);
    }
}

namespace {
    use Bugo\BenchmarkUtils\BenchmarkRunner;
    use Bugo\BenchmarkUtils\ReportGenerator;
    use Bugo\BenchmarkUtils\BenchmarkRunnerFileMtimeShim;

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
                throw new Exception('Compilation failed');
            }
        };
    });

    afterEach(function () {
        BenchmarkRunnerFileMtimeShim::reset();
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
                return new stdClass();
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
            $runner->setCode('.test { color: red; }');
            $runner->setRuns(1);
            $runner->setWarmupRuns(0);
            $runner->setOutputDir($this->tempDir);
            $runner->addCompiler('test-compiler', fn () => $this->mockCompiler);
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
            $runner->addCompiler('vendor/package-name', fn () => $this->mockCompiler);
            $runner->run();

            expect($this->tempDir . DIRECTORY_SEPARATOR . 'result-vendor-package-name.css')->toBeFile();
        });

        test('handles compiler exception gracefully', function () {
            $runner = new BenchmarkRunner();
            $runner->setCode('.test { color: red; }');
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
            $runner->setCode('.test { color: red; }');
            $runner->setRuns(1);
            $runner->setWarmupRuns(0);
            $runner->setOutputDir($this->tempDir);
            $runner->addCompiler('unsupported-compiler', fn () => new stdClass());

            $results = $runner->run();

            expect($results)->toHaveKey('unsupported-compiler')
                ->and($results['unsupported-compiler']['time'])->toContain('Error');
        });

        test('warmup runs compileString', function () {
            $runner = new BenchmarkRunner();
            $runner->setCode('.test { color: red; }');
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

        test('warmup and compile use compileFile when source file is provided', function () {
            $runner     = new BenchmarkRunner();
            $sourceFile = $this->tempDir . '/input.scss';

            file_put_contents($sourceFile, '.test { color: red; }');

            $runner->setSourceFile($sourceFile);
            $runner->setRuns(1);
            $runner->setWarmupRuns(2);
            $runner->setOutputDir($this->tempDir);

            $mockCompiler = new class {
                public int $compileCount = 0;

                public function compileFile(string $file): object
                {
                    $this->compileCount++;

                    return new class {
                        public function getCss(): string
                        {
                            return '.test { color: green; }';
                        }

                        public function getSourceMap(): ?string
                        {
                            return '{"sources": ["input.scss"]}';
                        }
                    };
                }
            };

            $runner->addCompiler('file-object-compiler', fn () => $mockCompiler);

            $results = $runner->run();

            expect($mockCompiler->compileCount)->toBe(3)
                ->and($results)->toHaveKey('file-object-compiler')
                ->and($this->tempDir . DIRECTORY_SEPARATOR . 'result-file-object-compiler.css')->toBeFile()
                ->and($this->tempDir . DIRECTORY_SEPARATOR . 'result-file-object-compiler.css.map')->toBeFile()
                ->and(file_get_contents($this->tempDir . DIRECTORY_SEPARATOR . 'result-file-object-compiler.css.map'))
                ->toBe('{"sources": ["input.scss"]}');
        });

        test('compileFile supports plain string results', function () {
            $runner     = new BenchmarkRunner();
            $sourceFile = $this->tempDir . '/input.scss';

            file_put_contents($sourceFile, '.test { color: red; }');

            $runner
                ->setSourceFile($sourceFile)
                ->setRuns(1)
                ->setWarmupRuns(0)
                ->setOutputDir($this->tempDir)
                ->addCompiler('file-string-compiler', fn () => new class {
                    public function compileFile(string $file): string
                    {
                        return '.test { color: black; }';
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

        test('handles compiler with result object', function () {
            $runner = new BenchmarkRunner();
            $runner->setCode('.test { color: red; }');
            $runner->setRuns(1);
            $runner->setWarmupRuns(0);
            $runner->setOutputDir($this->tempDir);
            $runner->addCompiler('object-compiler', fn () => $this->mockCompilerWithResultObject);

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
                ->addCompiler('object-compiler', fn () => $this->mockCompilerWithResultObject);

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
                ->addCompiler('object-compiler', fn () => $this->mockCompilerWithResultObject);

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
                ->addCompiler('object-compiler', fn () => $this->mockCompilerWithResultObject);

            $runner->run();

            expect(file_get_contents($mapFile))->toBe('{"sources": ["cached.scss"]}');
        });

        test('regenerates sourcemap when filemtime cannot be determined', function () {
            $runner     = new BenchmarkRunner();
            $sourceFile = $this->tempDir . '/input.scss';
            $mapFile    = $this->tempDir . DIRECTORY_SEPARATOR . 'result-object-compiler.css.map';

            file_put_contents($sourceFile, '.test { color: red; }');
            file_put_contents($mapFile, '{"sources": ["cached.scss"]}');

            BenchmarkRunnerFileMtimeShim::set($mapFile, false);

            $runner
                ->setCode('.test { color: red; }')
                ->setSourceFile($sourceFile)
                ->setRuns(1)
                ->setWarmupRuns(0)
                ->setOutputDir($this->tempDir)
                ->addCompiler('object-compiler', fn () => $this->mockCompilerWithResultObject);

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
                ->addCompiler('test/compiler', fn () => $this->mockCompiler);

            expect($result)->toBeInstanceOf(BenchmarkRunner::class);
        });
    });
}
