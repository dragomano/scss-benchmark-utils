<?php

declare(strict_types=1);

namespace {
    use Bugo\BenchmarkUtils\CompilationResult;
    use Bugo\BenchmarkUtils\LegacyCompilerAdapter;
    use Bugo\BenchmarkUtils\UnsupportedCompilerException;

    describe('LegacyCompilerAdapter', function () {
        test('warmup uses compileString when source file is not provided', function () {
            $compiler = new class {
                public int $compileCount = 0;

                public function compileString(string $scss): string
                {
                    $this->compileCount++;

                    return '.test { color: blue; }';
                }
            };

            $adapter = new LegacyCompilerAdapter($compiler, 'legacy/compiler');

            $adapter->warmup('.test { color: red; }', null);

            expect($compiler->compileCount)->toBe(1);
        });

        test('warmup uses compileFile when source file is provided', function () {
            $compiler = new class {
                public int $compileCount = 0;

                public function compileFile(string $file): string
                {
                    $this->compileCount++;

                    return '.test { color: green; }';
                }
            };

            $adapter = new LegacyCompilerAdapter($compiler, 'legacy/compiler');

            $adapter->warmup(null, '/tmp/input.scss');

            expect($compiler->compileCount)->toBe(1);
        });

        test('compile returns normalized string result from compileString', function () {
            $compiler = new class {
                public function compileString(string $scss): string
                {
                    return '.test { color: blue; }';
                }
            };

            $adapter = new LegacyCompilerAdapter($compiler, 'legacy/compiler');
            $result  = $adapter->compile('.test { color: red; }', null);

            expect($result)->toBeInstanceOf(CompilationResult::class)
                ->and($result->css)->toBe('.test { color: blue; }')
                ->and($result->sourceMap)->toBeNull();
        });

        test('compile returns normalized object result from compileFile', function () {
            $compiler = new class {
                public function compileFile(string $file): object
                {
                    return new class {
                        public function getCss(): string
                        {
                            return '.test { color: green; }';
                        }

                        public function getSourceMap(): string
                        {
                            return '{"sources": ["input.scss"]}';
                        }
                    };
                }
            };

            $adapter = new LegacyCompilerAdapter($compiler, 'legacy/compiler');
            $result  = $adapter->compile(null, '/tmp/input.scss');

            expect($result->css)->toBe('.test { color: green; }')
                ->and($result->sourceMap)->toBe('{"sources": ["input.scss"]}');
        });

        test('compile skips sourcemap extraction when disabled', function () {
            $compiler = new class {
                public function compileString(string $scss): object
                {
                    return new class {
                        public function getCss(): string
                        {
                            return '.test { color: orange; }';
                        }

                        public function getSourceMap(): string
                        {
                            return '{"sources": ["test.scss"]}';
                        }
                    };
                }
            };

            $adapter = new LegacyCompilerAdapter($compiler, 'legacy/compiler');
            $result  = $adapter->compile('.test { color: red; }', null, false);

            expect($result->css)->toBe('.test { color: orange; }')
                ->and($result->sourceMap)->toBeNull();
        });

        test('throws exception for unsupported legacy compiler', function () {
            $adapter = new LegacyCompilerAdapter(new stdClass(), 'legacy/compiler');

            expect(fn() => $adapter->compile('.test { color: red; }', null))
                ->toThrow(
                    UnsupportedCompilerException::class,
                    'Compiler legacy/compiler factory must return a CompilerAdapterInterface or a supported legacy compiler',
                );
        });

        test('throws exception when source file is provided but compileFile is not supported', function () {
            $compiler = new class {
                public function compileString(string $scss): string
                {
                    return '.test { color: blue; }';
                }
            };

            $adapter = new LegacyCompilerAdapter($compiler, 'legacy/compiler');

            expect(fn() => $adapter->compile(null, '/tmp/input.scss'))
                ->toThrow(
                    UnsupportedCompilerException::class,
                    'Compiler legacy/compiler factory must return a CompilerAdapterInterface or a supported legacy compiler',
                );
        });
    });
}
