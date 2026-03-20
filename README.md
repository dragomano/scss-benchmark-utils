# Benchmark suite for SCSS/Sass compilers

![PHP](https://img.shields.io/badge/PHP-^8.2-blue.svg?style=flat)
[![Coverage Status](https://coveralls.io/repos/github/dragomano/scss-benchmark-utils/badge.svg?branch=main)](https://coveralls.io/github/dragomano/scss-benchmark-utils?branch=main)

---

## Requirements

- PHP >= 8.2

---

## Installation

```bash
composer require bugo/scss-benchmark-utils
```

## Usage examples

### OsDetector

Detects the current operating system:

```php
<?php

use Bugo\BenchmarkUtils\OsDetector;

$os = OsDetector::detect();
// Windows: "Windows 11 24H2 (Build 26100.2033)"
// Linux: "Linux 5.15.0-generic"
// macOS: "Darwin 23.0.0"
```

### ScssGenerator

Generates complex SCSS code for benchmarking compilers:

The generator targets modern Sass implementations and includes CSS Color 4 syntax such as `hwb()`, `lab()`, `lch()`, `oklab()`, `oklch()`, and `color()` with spaces like `srgb`, `display-p3`, `rec2020`, and `xyz`.

```php
<?php

use Bugo\BenchmarkUtils\ScssGenerator;

// Generate with default parameters (100 classes, 3 nested levels)
$scss = ScssGenerator::generate();

// Generate with custom parameters
$scss = ScssGenerator::generate(
    numClasses: 500, // Number of classes to generate
    nestedLevels: 5  // Depth of nesting
);

// Save to file for benchmarking
file_put_contents('benchmark.scss', $scss);
```

### BenchmarkRunner

Run benchmarks for multiple SCSS compilers:

```php
<?php

use Bugo\BenchmarkUtils\ScssGenerator;
use Bugo\BenchmarkUtils\BenchmarkRunner;
use ScssPhp\ScssPhp\Compiler as ScssCompiler;
use ScssPhp\ScssPhp\OutputStyle;

$scss = ScssGenerator::generate(200, 4);
file_put_contents('generated.scss', $scss, LOCK_EX);

$results = (new BenchmarkRunner())
    ->setScssCode($scss)
    ->setRuns(10)
    ->setWarmupRuns(2)
    ->setOutputDir(__DIR__)
    ->addCompiler('scssphp/scssphp', function() {
        $compiler = new ScssCompiler();
        $compiler->setOutputStyle(OutputStyle::COMPRESSED);

        return $compiler;
    })
    ->addCompiler('another/compiler', function() {
        return new AnotherCompiler();
    })
    ->run();

echo BenchmarkRunner::formatTable($results);

BenchmarkRunner::updateMarkdownFile('benchmark.md', $results);
```

The generated SCSS includes:
- Variables with CSS functions (`abs()`, `round()`, `ceil()`, `floor()`)
- Custom functions (`@function`)
- Mixins (`@mixin`, `@include`)
- Conditional statements (`@if`, `@else`)
- Loops (`@for`, `@while`)
- Color manipulation functions (`lighten()`, `darken()`, `saturate()`, `desaturate()`, `mix()`)
- Modern CSS Color 4 spaces (`rgb`, `hsl`, `hwb`, `lab`, `lch`, `oklab`, `oklch`, `color(srgb ...)`, `color(display-p3 ...)`, `color(rec2020 ...)`, `color(xyz ...)`)
- CSS comparison functions (`min()`, `max()`, `clamp()`)
- Nested selectors with `&` parent selector
- Single-line comments (`//`) and multi-line comments (`/* */`)
- Important comments (`/*!`)
- Interpolated comments
