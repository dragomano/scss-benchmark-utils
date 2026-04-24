<?php

declare(strict_types=1);

namespace Bugo\BenchmarkUtils;

interface CompilerAdapterInterface
{
    public function warmup(?string $code, ?string $sourceFile): void;

    public function compile(?string $code, ?string $sourceFile, bool $includeSourceMap = true): CompilationResult;
}
