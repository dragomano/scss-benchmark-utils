<?php

declare(strict_types=1);

namespace Bugo\BenchmarkUtils;

final readonly class CompilationResult
{
    public function __construct(
        public string $css,
        public ?string $sourceMap = null,
    ) {}
}
