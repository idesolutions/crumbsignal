<?php
/**
 * PHPUnit bootstrap for CrumbSignal's unit tests.
 *
 * @package CrumbSignal
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once __DIR__ . '/wp-fakes.php';
require_once dirname( __DIR__ ) . '/includes/class-crumbsignal-settings.php';
