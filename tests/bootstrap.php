<?php
/**
 * PHPUnit bootstrap: WordPress test suite + WooCommerce + LPG Commerce Core.
 *
 * Uses the local WordPress install's code with a SEPARATE test database
 * (configured in tests/wp-tests-config.php). The test suite drops and
 * recreates tables, so that file must never point at the site database.
 *
 * @package LPG\Commerce\Tests
 */

require dirname( __DIR__ ) . '/vendor/autoload.php';

$lpg_tests_config = __DIR__ . '/wp-tests-config.php';
if ( ! file_exists( $lpg_tests_config ) ) {
	fwrite( STDERR, "Missing tests/wp-tests-config.php. Copy tests/wp-tests-config.sample.php and fill in the TEST database.\n" );
	exit( 1 );
}
// Load the config ourselves (bypasses the WP_PHPUNIT__TABLE_PREFIX env override)
// and fail closed unless it targets a *_test database with the wptests_ prefix.
define( 'WP_TESTS_CONFIG_FILE_PATH', $lpg_tests_config );
require_once $lpg_tests_config;
if ( 'gas_retailer_db' === DB_NAME || ! str_ends_with( DB_NAME, '_test' ) || 'wptests_' !== $table_prefix ) {
	fwrite( STDERR, "Refusing to run: tests must use a *_test database with the wptests_ table prefix.\n" );
	exit( 1 );
}

$lpg_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
if ( ! $lpg_tests_dir ) {
	$lpg_tests_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}

require $lpg_tests_dir . '/includes/functions.php';

// Isolate uploads (DEF-009): the test suite deletes attachments and their files,
// so it must never share the dev site's wp-content/uploads directory.
$lpg_test_uploads = sys_get_temp_dir() . '/lpg-test-uploads';
if ( ! is_dir( $lpg_test_uploads ) ) {
	mkdir( $lpg_test_uploads, 0777, true );
}
tests_add_filter(
	'upload_dir',
	static function ( array $dirs ) use ( $lpg_test_uploads ): array {
		$base_url        = 'http://example.org/lpg-test-uploads';
		$dirs['path']    = $lpg_test_uploads . $dirs['subdir'];
		$dirs['url']     = $base_url . $dirs['subdir'];
		$dirs['basedir'] = $lpg_test_uploads;
		$dirs['baseurl'] = $base_url;
		return $dirs;
	}
);

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
		require WP_PLUGIN_DIR . '/lpg-commerce-core/lpg-commerce-core.php';
	}
);

// Create WooCommerce tables in the test database.
tests_add_filter(
	'setup_theme',
	static function () {
		WC_Install::install();
	}
);

require $lpg_tests_dir . '/includes/bootstrap.php';
