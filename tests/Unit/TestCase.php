<?php
declare( strict_types=1 );

namespace CrumbSignal\Tests\Unit;

use Brain\Monkey;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

abstract class TestCase extends PolyfillTestCase {
	protected function set_up(): void {
		Monkey\setUp();
		Monkey\Functions\stubTranslationFunctions();
	}

	protected function tear_down(): void {
		Monkey\tearDown();
	}
}
