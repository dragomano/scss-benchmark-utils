<?php

declare(strict_types=1);

namespace Bugo\BenchmarkUtils;

use Random\RandomException;

class ScssGenerator
{
    private const MODERN_COLOR_SPACES = [
        'rgb',
        'hwb',
        'hsl',
        'srgb',
        'srgb-linear',
        'display-p3',
        'display-p3-linear',
        'a98-rgb',
        'prophoto-rgb',
        'rec2020',
        'xyz',
        'xyz-d50',
        'xyz-d65',
        'lab',
        'lch',
        'oklab',
        'oklch',
    ];

    /**
     * @throws RandomException
     */
    public static function generate(int $numClasses = 100, int $nestedLevels = 3): string
    {
        $scss = self::generateRootCustomProperties();
        $scss .= self::generateVariables();
        $scss .= self::generateComments();
        $scss .= self::generateFunctions();
        $scss .= self::generateMixins();
        $scss .= self::generateClasses($numClasses, $nestedLevels);
        $scss .= self::generateForLoop();
        $scss .= self::generateColorClasses();

        return $scss . self::generateWhileLoop();
    }

    /**
     * @throws RandomException
     */
    private static function generateRootCustomProperties(): string
    {
        $scss = ':root {' . PHP_EOL;
        $scss .= '  --rotation: ' . self::randomAngle() . ';' . PHP_EOL;

        return $scss . ('}' . PHP_EOL . PHP_EOL);
    }

    /**
     * @throws RandomException
     */
    private static function generateVariables(): string
    {
        $scss = '$primary-color: #007bff;' . PHP_EOL;
        $scss .= '$secondary-color: #6c757d;' . PHP_EOL;
        $scss .= '$font-size: 14px;' . PHP_EOL;
        $scss .= '$border-radius: 5px;' . PHP_EOL;
        $scss .= '$max-width: max(800px, 50vw);' . PHP_EOL;
        $scss .= '$min-padding: min(10px, 2vw);' . PHP_EOL;
        $scss .= '$clamped-size: clamp(12px, 2.5vw, 20px);' . PHP_EOL;

        for ($i = 0; $i < 20; $i++) {
            $randomVal = random_int(-50, 50);
            $scss .= '$var' . $i . ': ' . 'abs(' . $randomVal . 'px);' . PHP_EOL;
            $scss .= '$rounded-var' . $i . ': ' . 'round(' . (random_int(0, 100) / 3.14) . ');' . PHP_EOL;
            $scss .= '$ceiled-var' . $i . ': ' . 'ceil(' . (random_int(0, 100) / 2.7) . 'px);' . PHP_EOL;
            $scss .= '$floored-var' . $i . ': ' . 'floor(' . (random_int(0, 100) / 1.8) . 'px);' . PHP_EOL;
        }

        return $scss . PHP_EOL;
    }

    private static function generateComments(): string
    {
        $scss = '// Regular single-line comment' . PHP_EOL;
        $scss .= '/* Regular block comment */' . PHP_EOL;
        $scss .= '/* Interpolated block comment: #{$primary-color} */' . PHP_EOL;
        $scss .= '/*! Important block comment */' . PHP_EOL;

        return $scss . ('/*! Interpolated important comment: #{$secondary-color} */' . PHP_EOL);
    }

    private static function generateFunctions(): string
    {
        $scss = '@function calculate-size($base, $multiplier: 1) {' . PHP_EOL;
        $scss .= '  @return $base * $multiplier;' . PHP_EOL;

        return $scss . ('}' . PHP_EOL . PHP_EOL);
    }

    private static function generateMixins(): string
    {
        $scss = '@mixin flex-center {' . PHP_EOL;
        $scss .= '  display: flex;' . PHP_EOL;
        $scss .= '  justify-content: center;' . PHP_EOL;
        $scss .= '  align-items: center;' . PHP_EOL;
        $scss .= '}' . PHP_EOL . PHP_EOL;

        $scss .= '@mixin button-style($color) {' . PHP_EOL;
        $scss .= '  background-color: lighten($color, 5%);' . PHP_EOL;
        $scss .= '  border: 1px solid saturate($color, 20%);' . PHP_EOL;
        $scss .= '  border-radius: calc($border-radius + 2px);' . PHP_EOL;
        $scss .= '  padding: max(8px, $min-padding) max(15px, calc($min-padding * 2));' . PHP_EOL;
        $scss .= '  &:hover {' . PHP_EOL;
        $scss .= '    background-color: desaturate($color, 10%);' . PHP_EOL;
        $scss .= '    transform: scale(calc(1.05));' . PHP_EOL;
        $scss .= '  }' . PHP_EOL;
        $scss .= '}' . PHP_EOL . PHP_EOL;

        $scss .= '@mixin color-variations($base-color) {' . PHP_EOL;
        $scss .= '  .light { color: lighten($base-color, 20%); }' . PHP_EOL;
        $scss .= '  .dark { color: darken($base-color, 15%); }' . PHP_EOL;
        $scss .= '  .saturated { color: saturate($base-color, 30%); }' . PHP_EOL;
        $scss .= '  .desaturated { color: desaturate($base-color, 25%); }' . PHP_EOL;
        $scss .= '  .hue-rotated { filter: hue-rotate(45deg); }' . PHP_EOL;

        return $scss . ('}' . PHP_EOL . PHP_EOL);
    }

    /**
     * @throws RandomException
     */
    private static function generateClasses(int $numClasses, int $nestedLevels): string
    {
        $scss = '';

        for ($i = 0; $i < $numClasses; $i++) {
            $scss .= '.class-' . $i . ' {' . PHP_EOL;
            $scss .= '  background-color: mix($primary-color, $secondary-color, ' . random_int(20, 80) . '%);' . PHP_EOL;
            $scss .= '  font-size: clamp($clamped-size, calculate-size($font-size, ' . (random_int(1, 3)) . '), 24px);' . PHP_EOL;
            $scss .= '  padding: max($var' . random_int(0, 19) . ', $min-padding);' . PHP_EOL;
            $scss .= '  margin: calc($var' . random_int(0, 19) . ' + 5px);' . PHP_EOL;
            $scss .= '  border-radius: $border-radius;' . PHP_EOL;
            $scss .= '  max-width: $max-width;' . PHP_EOL;
            $scss .= '  @include color-variations($primary-color);' . PHP_EOL . PHP_EOL;

            $randomVal = random_int(0, 1);
            $scss .= '  @if ' . $randomVal . ' == 1 {' . PHP_EOL;
            $scss .= '    color: lighten($primary-color, 40%);' . PHP_EOL;
            $scss .= '    text-shadow: 1px 1px 2px rgba(0,0,0,0.3);' . PHP_EOL;
            $scss .= '  } @else {' . PHP_EOL;
            $scss .= '    color: darken($primary-color, 20%);' . PHP_EOL;
            $scss .= '    border: 1px solid saturate($primary-color, 15%);' . PHP_EOL;
            $scss .= '  }' . PHP_EOL . PHP_EOL;

            for ($level = 1; $level <= $nestedLevels; $level++) {
                $scss .= str_repeat('  ', $level) . '&.nested-' . $level . ' {' . PHP_EOL;
                $scss .= str_repeat('  ', $level + 1) . 'filter: hue-rotate(' . (random_int(0, 360)) . 'deg) saturate(' . (100 + random_int(-20, 20)) . '%);' . PHP_EOL;
                $scss .= str_repeat('  ', $level + 1) . 'background-color: lighten($secondary-color, ' . random_int(10, 30) . '%);' . PHP_EOL;
                $scss .= str_repeat('  ', $level + 1) . '@include flex-center;' . PHP_EOL;
                $scss .= str_repeat('  ', $level + 1) . 'transform: scale(calc(1 + ' . (random_int(1, 10) / 100) . '));' . PHP_EOL;
            }

            for ($level = $nestedLevels; $level >= 1; $level--) {
                $scss .= str_repeat('  ', $level) . '}' . PHP_EOL;
            }

            $scss .= PHP_EOL;
            $scss .= '  &:hover {' . PHP_EOL;
            $scss .= '    @include button-style(lighten($primary-color, 10%));' . PHP_EOL;
            $scss .= '  }' . PHP_EOL;

            $scss .= '}' . PHP_EOL . PHP_EOL;
        }

        return $scss;
    }

    private static function generateForLoop(): string
    {
        $scss = '@for $i from 1 through 20 {' . PHP_EOL;
        $scss .= '  .for-class-#{$i} {' . PHP_EOL;
        $scss .= '    width: calc(10px * $i);' . PHP_EOL;
        $scss .= '    height: min(50px, calc(20px + $i * 2px));' . PHP_EOL;
        $scss .= '    @include button-style(saturate($secondary-color, calc($i * 2%)));' . PHP_EOL;
        $scss .= '    border-radius: clamp(3px, calc($i * 2px), 15px);' . PHP_EOL;
        $scss .= '    filter: hue-rotate(calc($i * 18deg));' . PHP_EOL;
        $scss .= '  }' . PHP_EOL;

        return $scss . ('}' . PHP_EOL . PHP_EOL);
    }

    /**
     * @throws RandomException
     */
    private static function generateColorClasses(): string
    {
        $scss = '$color-names: red, green, blue, yellow, magenta, cyan;' . PHP_EOL;
        $scss .= '$color-values: #ff0000, #00ff00, #0000ff, #ffff00, #ff00ff, #00ffff;' . PHP_EOL;
        $scss .= '@for $i from 1 through length($color-names) {' . PHP_EOL;
        $scss .= '  $name: nth($color-names, $i);' . PHP_EOL;
        $scss .= '  $color: nth($color-values, $i);' . PHP_EOL;
        $scss .= '  .color-#{"#{$name}"} {' . PHP_EOL;
        $scss .= '    background-color: lighten($color, 10%);' . PHP_EOL;
        $scss .= '    border: 2px solid saturate($color, 20%);' . PHP_EOL;
        $scss .= '    &:hover {' . PHP_EOL;
        $scss .= '      background-color: desaturate($color, 15%);' . PHP_EOL;
        $scss .= '      transform: rotate(calc(var(--rotation, 0deg) + 5deg));' . PHP_EOL;
        $scss .= '    }' . PHP_EOL;
        $scss .= '  }' . PHP_EOL;
        $scss .= '}' . PHP_EOL . PHP_EOL;

        $scss .= self::generateModernColorVariables();

        return $scss . self::generateModernColorClasses();
    }

    /**
     * @throws RandomException
     */
    private static function generateModernColorVariables(): string
    {
        $scss = '$modern-color-spaces: ' . implode(', ', self::MODERN_COLOR_SPACES) . ';' . PHP_EOL;

        foreach (self::MODERN_COLOR_SPACES as $index => $space) {
            $scss .= '$modern-color-' . $index . ': ' . self::generateColorBySpace($space) . ';' . PHP_EOL;
        }

        return $scss . PHP_EOL;
    }

    /**
     * @throws RandomException
     */
    private static function generateModernColorClasses(): string
    {
        $scss = '@for $i from 1 through length($modern-color-spaces) {' . PHP_EOL;
        $scss .= '  $space: nth($modern-color-spaces, $i);' . PHP_EOL;
        $scss .= '  $color: nth((';

        foreach (array_keys(self::MODERN_COLOR_SPACES) as $index) {
            $scss .= '$modern-color-' . $index . ', ';
        }

        $scss = rtrim($scss, ', ');
        $scss .= '), $i);' . PHP_EOL;
        $scss .= '  .modern-color-#{$i}-#{$space} {' . PHP_EOL;
        $scss .= '    color: $color;' . PHP_EOL;
        $scss .= '    background-color: $color;' . PHP_EOL;
        $scss .= '    border-color: $color;' . PHP_EOL;
        $scss .= '    outline: 1px solid $color;' . PHP_EOL;
        $scss .= '    box-shadow: 0 0 0 2px $color;' . PHP_EOL;
        $scss .= '  }' . PHP_EOL;
        $scss .= '}' . PHP_EOL . PHP_EOL;

        for ($i = 0; $i < 12; $i++) {
            $scss .= '.random-modern-color-' . $i . ' {' . PHP_EOL;
            $scss .= '  background-image: linear-gradient(135deg, ' . self::generateRandomColor() . ', ' . self::generateRandomColor() . ');' . PHP_EOL;
            $scss .= '  border: 1px solid ' . self::generateRandomColor() . ';' . PHP_EOL;
            $scss .= '  color: ' . self::generateRandomColor() . ';' . PHP_EOL;
            $scss .= '}' . PHP_EOL . PHP_EOL;
        }

        return $scss;
    }

    /**
     * @throws RandomException
     */
    private static function generateRandomColor(): string
    {
        return self::generateColorBySpace(self::MODERN_COLOR_SPACES[array_rand(self::MODERN_COLOR_SPACES)]);
    }

    /**
     * @throws RandomException
     */
    private static function generateColorBySpace(string $space): string
    {
        return match ($space) {
            'rgb' => 'rgb(' . random_int(0, 255) . ' ' . random_int(0, 255) . ' ' . random_int(0, 255) . ')',
            'hwb' => 'hwb(' . self::randomAngle() . ' ' . self::randomPercent() . ' ' . self::randomPercent() . ')',
            'hsl' => 'hsl(' . self::randomAngle() . ' ' . self::randomPercent() . ' ' . self::randomPercent() . ')',
            'srgb',
            'srgb-linear',
            'display-p3',
            'display-p3-linear',
            'a98-rgb',
            'prophoto-rgb',
            'rec2020',
            'xyz',
            'xyz-d50',
            'xyz-d65' => 'color(' . $space . ' ' . self::randomUnitChannel() . ' ' . self::randomUnitChannel() . ' ' . self::randomUnitChannel() . ')',
            'lab' => 'lab(' . self::randomPercent() . ' ' . random_int(-125, 125) . ' ' . random_int(-125, 125) . ')',
            'lch' => 'lch(' . self::randomPercent() . ' ' . self::randomFloat(0, 150, 1) . ' ' . self::randomAngle() . ')',
            'oklab' => 'oklab(' . self::randomPercent() . ' ' . self::randomFloat(-0.4, 0.4) . ' ' . self::randomFloat(-0.4, 0.4) . ')',
            'oklch' => 'oklch(' . self::randomPercent() . ' ' . self::randomFloat(0, 0.4) . ' ' . self::randomAngle() . ')',
        };
    }

    /**
     * @throws RandomException
     */
    private static function randomPercent(): string
    {
        return random_int(0, 100) . '%';
    }

    /**
     * @throws RandomException
     */
    private static function randomAngle(): string
    {
        return random_int(0, 360) . 'deg';
    }

    /**
     * @throws RandomException
     */
    private static function randomUnitChannel(): string
    {
        return self::randomFloat(0, 1);
    }

    /**
     * @throws RandomException
     */
    private static function randomFloat(float $min, float $max, int $precision = 3): string
    {
        $scale = 10 ** $precision;
        $value = random_int((int) round($min * $scale), (int) round($max * $scale)) / $scale;

        return number_format($value, $precision, '.', '');
    }

    private static function generateWhileLoop(): string
    {
        $scss = '$counter: 1;' . PHP_EOL;
        $scss .= '@while $counter <= 15 {' . PHP_EOL;
        $scss .= '  .while-class-#{$counter} {' . PHP_EOL;
        $scss .= '    opacity: calc(0.1 * $counter);' . PHP_EOL;
        $scss .= '    z-index: $counter;' . PHP_EOL;
        $scss .= '    font-size: round(max(10px, calc(8px + $counter * 0.5px)));' . PHP_EOL;
        $scss .= '  }' . PHP_EOL;
        $scss .= '  $counter: $counter + 1;' . PHP_EOL;

        return $scss . ('}' . PHP_EOL . PHP_EOL);
    }
}
