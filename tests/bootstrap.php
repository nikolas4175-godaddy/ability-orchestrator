<?php
/**
 * PHPUnit bootstrap for wp-env tests-cli.
 *
 * @package Baton
 */

declare( strict_types=1 );

$baton_root = dirname( __DIR__ );

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php. Run tests via wp-env (npm run test:php).\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

require $_tests_dir . '/includes/functions.php';

/**
 * Load Baton before the test suite runs.
 */
function baton_manually_load_plugin(): void {
	require dirname( __DIR__ ) . '/baton.php';
	require dirname( __DIR__ ) . '/tests/php/fixtures/test-abilities.php';
}

tests_add_filter( 'muplugins_loaded', 'baton_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
