<?php
/**
 * Plugin bootstrap tests.
 *
 * @package LPG\Commerce\Tests
 */

use LPG\Commerce\Installer;
use LPG\Commerce\Plugin;
use LPG\Commerce\Service;

/**
 * Tests for LPG\Commerce\Plugin and LPG\Commerce\Installer.
 */
class PluginTest extends WP_UnitTestCase {

	public function test_plugin_boots_with_woocommerce_loaded(): void {
		$this->assertTrue( Plugin::woocommerce_active() );
		$this->assertGreaterThan( 0, did_action( 'lpg_loaded' ) );
	}

	public function test_services_filter_ignores_non_service_classes(): void {
		$valid = get_class(
			new class() implements Service {
				public function register(): void {}
			}
		);

		$filter   = static fn() => array( $valid, \stdClass::class, 'Not\\A\\Class', 42 );
		add_filter( 'lpg_services', $filter );
		$services = Plugin::services();
		remove_filter( 'lpg_services', $filter );

		$this->assertSame( array( $valid ), $services );
	}

	public function test_install_stores_version_and_settings_without_autoload(): void {
		delete_option( Installer::VERSION_OPTION );
		delete_option( Installer::SETTINGS_OPTION );

		Installer::install();

		$this->assertSame( LPG_COMMERCE_VERSION, get_option( Installer::VERSION_OPTION ) );
		$this->assertSame( Installer::default_settings(), get_option( Installer::SETTINGS_OPTION ) );

		$autoloaded = wp_load_alloptions();
		$this->assertArrayNotHasKey( Installer::VERSION_OPTION, $autoloaded );
		$this->assertArrayNotHasKey( Installer::SETTINGS_OPTION, $autoloaded );
	}

	public function test_install_does_not_overwrite_existing_settings(): void {
		update_option( Installer::SETTINGS_OPTION, array( 'whatsapp_number' => '254700000000' ), false );

		Installer::install();

		$this->assertSame( array( 'whatsapp_number' => '254700000000' ), get_option( Installer::SETTINGS_OPTION ) );
	}

	public function test_hpos_compatibility_is_declared(): void {
		$compat = \Automattic\WooCommerce\Utilities\FeaturesUtil::get_compatible_plugins_for_feature( 'custom_order_tables' );
		$this->assertContains( 'lpg-commerce-core/lpg-commerce-core.php', $compat['compatible'] );
	}
}
