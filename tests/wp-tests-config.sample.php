<?php
/**
 * Template for tests/wp-tests-config.php (git-ignored).
 *
 * WARNING: the test suite DROPS ALL TABLES in DB_NAME. Use a dedicated
 * test database, never the site database (gas_retailer_db).
 *
 * @package LPG\Commerce\Tests
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/gas-retailer/' );

define( 'DB_NAME', 'gas_retailer_test' );
define( 'DB_USER', 'your_db_user' );
define( 'DB_PASSWORD', 'your_db_password' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'LPG Test Site' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WP_DEBUG', true );
define( 'WPLANG', '' );
