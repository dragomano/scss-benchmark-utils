<?php

declare(strict_types=1);

namespace Bugo\BenchmarkUtils;

use Random\RandomException;

class ScssGenerator
{
    /**
     * @throws RandomException
     */
    public static function generate(int $numClasses = 100, int $nestedLevels = 3): string
    {
        $scss = self::generateVariables();
        $scss .= self::generateFunctions();
        $scss .= self::generateMixins();
        $scss .= self::generateClasses($numClasses, $nestedLevels);
        $scss .= self::generateForLoop();
        $scss .= self::generateColorClasses();
        $scss .= self::generateWhileLoop();

        return $scss;
    }

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

        return $scss;
    }

    private static function generateFunctions(): string
    {
        $scss = '@function calculate-size($base, $multiplier: 1) {' . PHP_EOL;
        $scss .= '  @return $base * $multiplier;' . PHP_EOL;
        $scss .= '}' . PHP_EOL;

        return $scss;
    }

    private static function generateMixins(): string
    {
        $scss = '@mixin flex-center {' . PHP_EOL;
        $scss .= '  display: flex;' . PHP_EOL;
        $scss .= '  justify-content: center;' . PHP_EOL;
        $scss .= '  align-items: center;' . PHP_EOL;
        $scss .= '}' . PHP_EOL;

        $scss .= '@mixin button-style($color) {' . PHP_EOL;
        $scss .= '  background-color: lighten($color, 5%);' . PHP_EOL;
        $scss .= '  border: 1px solid saturate($color, 20%);' . PHP_EOL;
        $scss .= '  border-radius: calc($border-radius + 2px);' . PHP_EOL;
        $scss .= '  padding: max(8px, $min-padding) max(15px, calc($min-padding * 2));' . PHP_EOL;
        $scss .= '  &:hover {' . PHP_EOL;
        $scss .= '    background-color: desaturate($color, 10%);' . PHP_EOL;
        $scss .= '    transform: scale(calc(1.05));' . PHP_EOL;
        $scss .= '  }' . PHP_EOL;
        $scss .= '}' . PHP_EOL;

        $scss .= '@mixin color-variations($base-color) {' . PHP_EOL;
        $scss .= '  .light { color: lighten($base-color, 20%); }' . PHP_EOL;
        $scss .= '  .dark { color: darken($base-color, 15%); }' . PHP_EOL;
        $scss .= '  .saturated { color: saturate($base-color, 30%); }' . PHP_EOL;
        $scss .= '  .desaturated { color: desaturate($base-color, 25%); }' . PHP_EOL;
        $scss .= '  .hue-rotated { filter: hue-rotate(45deg); }' . PHP_EOL;
        $scss .= '}' . PHP_EOL;

        return $scss;
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
            $scss .= '  @include color-variations($primary-color);' . PHP_EOL;

            $randomVal = random_int(0, 1);
            $scss .= '  @if ' . $randomVal . ' == 1 {' . PHP_EOL;
            $scss .= '    color: lighten($primary-color, 40%);' . PHP_EOL;
            $scss .= '    text-shadow: 1px 1px 2px rgba(0,0,0,0.3);' . PHP_EOL;
            $scss .= '  } @else {' . PHP_EOL;
            $scss .= '    color: darken($primary-color, 20%);' . PHP_EOL;
            $scss .= '    border: 1px solid saturate($primary-color, 15%);' . PHP_EOL;
            $scss .= '  }' . PHP_EOL;

            for ($level = 1; $level <= $nestedLevels; $level++) {
                $scss .= str_repeat('  ', $level) . '&.nested-' . $level . ' {' . PHP_EOL;
                $scss .= str_repeat('  ', $level + 1) . 'filter: hue-rotate(' . (random_int(0, 360)) . 'deg) saturate(' . (100 + random_int(-20, 20)) . '%);' . PHP_EOL;
                $scss .= str_repeat('  ', $level + 1) . 'background-color: lighten($secondary-color, ' . random_int(10, 30) . '%);' . PHP_EOL;
                $scss .= str_repeat('  ', $level + 1) . '@include flex-center;' . PHP_EOL;
                $scss .= str_repeat('  ', $level + 1) . 'transform: scale(calc(1 + ' . (random_int(1, 10) / 100) . '));' . PHP_EOL;
                $scss .= str_repeat('  ', $level) . '}' . PHP_EOL;
            }

            $scss .= '  &:hover {' . PHP_EOL;
            $scss .= '    @include button-style(lighten($primary-color, 10%));' . PHP_EOL;
            $scss .= '  }' . PHP_EOL;

            $scss .= '}' . PHP_EOL;
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
        $scss .= '}' . PHP_EOL;

        return $scss;
    }

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
        $scss .= '}' . PHP_EOL;

        return $scss;
    }

    private static function generateWhileLoop(): string
    {
        $scss = '$counter: 1;' . PHP_EOL;
        $scss .= '@while $counter <= 15 {' . PHP_EOL;
        $scss .= '  .while-class-#{$counter} {' . PHP_EOL;
        $scss .= '    opacity: calc(0.1 * $counter);' . PHP_EOL;
        $scss .= '    z-index: $counter;' . PHP_EOL;
        $scss .= '    font-size: max(10px, calc(8px + $counter * 0.5px));' . PHP_EOL;
        $scss .= '  }' . PHP_EOL;
        $scss .= '  $counter: $counter + 1;' . PHP_EOL;
        $scss .= '}' . PHP_EOL;

        return $scss;
    }
}
