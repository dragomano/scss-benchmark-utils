<?php

declare(strict_types=1);

namespace Bugo\BenchmarkUtils;

final readonly class LegacyCompilerAdapter implements CompilerAdapterInterface
{
    public function __construct(private object $compiler, private string $name) {}

    public function warmup(?string $code, ?string $sourceFile): void
    {
        $file = $sourceFile ?? '';

        if ($file !== '') {
            $this->compileFile($file);

            return;
        }

        $this->compileString($code ?? '');
    }

    public function compile(?string $code, ?string $sourceFile, bool $includeSourceMap = true): CompilationResult
    {
        $file = $sourceFile ?? '';

        if ($file !== '') {
            return $this->normalizeCompileResult($this->compileFile($file), $includeSourceMap);
        }

        return $this->normalizeCompileResult($this->compileString($code ?? ''), $includeSourceMap);
    }

    private function compileFile(string $file): mixed
    {
        if (method_exists($this->compiler, 'compileFile')) {
            return $this->compiler->compileFile($file);
        }

        throw new UnsupportedCompilerException($this->name);
    }

    private function compileString(string $scss): mixed
    {
        if (method_exists($this->compiler, 'compileString')) {
            return $this->compiler->compileString($scss);
        }

        throw new UnsupportedCompilerException($this->name);
    }

    private function normalizeCompileResult(mixed $result, bool $includeSourceMap): CompilationResult
    {
        if (is_object($result) && method_exists($result, 'getCss')) {
            return new CompilationResult(
                $result->getCss(),
                $includeSourceMap && method_exists($result, 'getSourceMap')
                    ? $result->getSourceMap()
                    : null,
            );
        }

        return new CompilationResult((string) $result);
    }
}
