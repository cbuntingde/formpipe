<?php
/**
 * PHPUnit bootstrap for FormPipe.
 *
 * Loads the WordPress test suite when available (via wp-phpunit/wp-phpunit
 * and WP_TESTS_DIR), and falls back to a no-WP environment that still
 * lets the smoke harness run.
 */

declare(strict_types=1);

// Smoke test path is independent of WP.
$_tests_dir = getenv('WP_TESTS_DIR');

if ( $_tests_dir && file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	require_once $_tests_dir . '/includes/functions.php';

	tests_add_filter(
		'muplugins_loaded',
		static function () {
			// Load FormPipe as a must-use plugin so its bootstrap runs.
			require dirname( __DIR__, 2 ) . '/formpipe.php';
		}
	);

	require $_tests_dir . '/includes/bootstrap.php';
} else {
	// No WP test suite available. The smoke test is still runnable
	// via `composer test:smoke`.
	define( 'WPINC', 'wp-includes' );
}
