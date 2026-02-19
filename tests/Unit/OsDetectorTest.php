<?php

use Bugo\BenchmarkUtils\OsDetector;

describe('detect()', function () {
	beforeEach(function () {
		$this->result = OsDetector::detect();
	});

	test('detect returns non-empty string', function () {
		expect($this->result)->toBeString()
			->and($this->result)->not->toBeEmpty();
	});

	test('detect returns valid os information', function () {
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			expect($this->result)->toContain('Windows');
		} else {
			expect($this->result)->toContain(' ');
		}
	});

	test('detect returns windows version on windows', function () {
		expect($this->result)->toContain('Windows')
			->and($this->result)->toContain('Build');
	})->onlyOnWindows();

	test('detect returns unix information on non-windows', function () {
		expect($this->result)->toBe(php_uname('s') . ' ' . php_uname('r'));
	})->skipOnWindows();
});

describe('getWindowsRelease()', function () {
	beforeEach(function () {
		$this->method = new ReflectionMethod(OsDetector::class, 'getWindowsRelease');
	});

	test('returns 26H1 for build 28000 and above', function () {
		expect($this->method->invoke(null, 28000))->toBe('26H1')
			->and($this->method->invoke(null, 29000))->toBe('26H1');
	});

	test('returns 25H2 for build 26200-27999', function () {
		expect($this->method->invoke(null, 26200))->toBe('25H2')
			->and($this->method->invoke(null, 27000))->toBe('25H2');
	});

	test('returns 24H2 for build 26100-26199', function () {
		expect($this->method->invoke(null, 26100))->toBe('24H2')
			->and($this->method->invoke(null, 26150))->toBe('24H2');
	});

	test('returns 23H2 for build 22631-22620', function () {
		expect($this->method->invoke(null, 22631))->toBe('23H2')
			->and($this->method->invoke(null, 23000))->toBe('23H2');
	});

	test('returns 22H2 for build 22621-22630', function () {
		expect($this->method->invoke(null, 22621))->toBe('22H2')
			->and($this->method->invoke(null, 22625))->toBe('22H2');
	});

	test('returns 21H2 for build 22000-22620', function () {
		expect($this->method->invoke(null, 22000))->toBe('21H2')
			->and($this->method->invoke(null, 22500))->toBe('21H2');
	});

	test('returns Unknown for build below 22000', function () {
		expect($this->method->invoke(null, 19041))->toBe('Unknown')
			->and($this->method->invoke(null, 0))->toBe('Unknown');
	});
})->onlyOnWindows();
