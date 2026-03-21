<?php

declare(strict_types=1);

use Bugo\BenchmarkUtils\ScssGenerator;

test('generate returns non-empty string', function () {
    $result = ScssGenerator::generate();

    expect($result)->toBeString()
        ->and($result)->not->toBeEmpty();
});

test('generate contains variables', function () {
    $result = ScssGenerator::generate();

    expect($result)->toContain(':root {')
        ->and($result)->toContain('--rotation:')
        ->and($result)->toContain('$primary-color:')
        ->and($result)->toContain('$secondary-color:')
        ->and($result)->toContain('$font-size:')
        ->and($result)->toContain('$border-radius:')
        ->and($result)->toContain('$max-width:')
        ->and($result)->toContain('$min-padding:')
        ->and($result)->toContain('$clamped-size:');
});

test('generate contains comments', function () {
    $result = ScssGenerator::generate();

    expect($result)->toContain('// Regular single-line comment')
        ->and($result)->toContain('/* Regular block comment */')
        ->and($result)->toContain('/* Interpolated block comment: #{$primary-color} */')
        ->and($result)->toContain('/*! Important block comment */')
        ->and($result)->toContain('/*! Interpolated important comment: #{$secondary-color} */');
});

test('generate contains functions', function () {
    $result = ScssGenerator::generate();

    expect($result)->toContain('@function calculate-size');
});

test('generate contains mixins', function () {
    $result = ScssGenerator::generate();

    expect($result)->toContain('@mixin flex-center')
        ->and($result)->toContain('@mixin button-style')
        ->and($result)->toContain('@mixin color-variations');
});

test('generate contains for loop', function () {
    $result = ScssGenerator::generate();

    expect($result)->toContain('@for $i from 1 through 20')
        ->and($result)->toContain('.for-class-');
});

test('generate contains while loop', function () {
    $result = ScssGenerator::generate();

    expect($result)->toContain('@while $counter <= 15')
        ->and($result)->toContain('.while-class-');
});

test('generate contains color classes', function () {
    $result = ScssGenerator::generate();

    expect($result)->toContain('$color-names:')
        ->and($result)->toContain('$color-values:')
        ->and($result)->toContain('$modern-color-spaces:')
        ->and($result)->toContain('.modern-color-')
        ->and($result)->toContain('.random-modern-color-');
});

test('generate contains modern SCSS color spaces', function () {
    $result = ScssGenerator::generate();

    expect($result)->toContain('rgb(')
        ->and($result)->toContain('hwb(')
        ->and($result)->toContain('hsl(')
        ->and($result)->toContain('color(srgb ')
        ->and($result)->toContain('color(srgb-linear ')
        ->and($result)->toContain('color(display-p3 ')
        ->and($result)->toContain('color(display-p3-linear ')
        ->and($result)->toContain('color(a98-rgb ')
        ->and($result)->toContain('color(prophoto-rgb ')
        ->and($result)->toContain('color(rec2020 ')
        ->and($result)->toContain('color(xyz ')
        ->and($result)->toContain('color(xyz-d50 ')
        ->and($result)->toContain('color(xyz-d65 ')
        ->and($result)->toContain('lab(')
        ->and($result)->toContain('lch(')
        ->and($result)->toContain('oklab(')
        ->and($result)->toContain('oklch(');
});

test('generate creates expected number of modern color classes', function () {
    $result = ScssGenerator::generate();

    $modernColorVariableCount = preg_match_all('/\$modern-color-\d+:\s*/', $result);
    $modernColorClassTemplateCount = substr_count($result, '.modern-color-#{$i}-#{$space}');
    $randomModernColorClassCount = substr_count($result, '.random-modern-color-');

    expect($modernColorVariableCount)->toBe(17)
        ->and($modernColorClassTemplateCount)->toBe(1)
        ->and($randomModernColorClassCount)->toBe(12);
});

test('generate with default parameters creates expected number of classes', function () {
    $result = ScssGenerator::generate();

    $classCount = substr_count($result, '.class-');
    expect($classCount)->toBe(100);
});

test('generate with custom numClasses creates correct number of classes', function () {
    $result = ScssGenerator::generate(50);

    $classCount = substr_count($result, '.class-');
    expect($classCount)->toBe(50);
});

test('generate with custom nestedLevels creates correct nesting', function () {
    $result = ScssGenerator::generate(10, 2);

    expect($result)->toMatch('/&\.nested-1\s*\{[\s\S]*?&\.nested-2\s*\{/');
});

test('generate contains css functions', function () {
    $result = ScssGenerator::generate();

    expect($result)->toContain('calc(')
        ->and($result)->toContain('max(')
        ->and($result)->toContain('min(')
        ->and($result)->toContain('clamp(');
});

test('generate contains scss color functions', function () {
    $result = ScssGenerator::generate();

    expect($result)->toContain('lighten(')
        ->and($result)->toContain('darken(')
        ->and($result)->toContain('saturate(')
        ->and($result)->toContain('desaturate(');
});

test('generate contains abs function', function () {
    $result = ScssGenerator::generate();

    expect($result)->toContain('abs(');
});

test('generate contains round ceil floor functions', function () {
    $result = ScssGenerator::generate();

    expect($result)->toContain('round(')
        ->and($result)->toContain('font-size: round(max(')
        ->and($result)->toContain('ceil(')
        ->and($result)->toContain('floor(');
});

test('generate contains hover states', function () {
    $result = ScssGenerator::generate();

    expect($result)->toContain('&:hover');
});

test('generate contains include statements', function () {
    $result = ScssGenerator::generate();

    expect($result)->toContain('@include flex-center')
        ->and($result)->toContain('@include button-style')
        ->and($result)->toContain('@include color-variations');
});

test('generate contains if else statements', function () {
    $result = ScssGenerator::generate();

    expect($result)->toContain('@if')
        ->and($result)->toContain('@else');
});

test('generate contains mix function', function () {
    $result = ScssGenerator::generate();

    expect($result)->toContain('mix(');
});

test('generate contains filter properties', function () {
    $result = ScssGenerator::generate();

    expect($result)->toContain('filter:')
        ->and($result)->toContain('hue-rotate(');
});

test('generate contains transform properties', function () {
    $result = ScssGenerator::generate();

    expect($result)->toContain('transform:')
        ->and($result)->toContain('scale(');
});

test('generate with zero nestedLevels still creates classes', function () {
    $result = ScssGenerator::generate(10, 0);

    expect($result)->toContain('.class-0')
        ->and($result)->not->toContain('.nested-1');
});

test('generate creates unique content on multiple calls', function () {
    $result1 = ScssGenerator::generate();
    $result2 = ScssGenerator::generate();

    expect($result1)->not->toBe($result2);
});
