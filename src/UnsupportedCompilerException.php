<?php

declare(strict_types=1);

namespace Bugo\BenchmarkUtils;

use RuntimeException;

final class UnsupportedCompilerException extends RuntimeException
{
    public function __construct(string $name)
    {
        parent::__construct("Compiler $name factory must return a CompilerAdapterInterface or a supported legacy compiler");
    }
}
