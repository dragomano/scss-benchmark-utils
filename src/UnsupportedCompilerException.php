<?php

declare(strict_types=1);

namespace Bugo\BenchmarkUtils;

use RuntimeException;

final class UnsupportedCompilerException extends RuntimeException
{
    public function __construct(string $name)
    {
        parent::__construct("Compiler $name does not support compileString or compileInPersistentMode");
    }
}
