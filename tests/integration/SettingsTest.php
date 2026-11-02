<?php
/**
 * Settings getter and WooCommerce settings tab tests.
 *
 * @package LPG\Commerce\Tests
 */

use LPG\Commerce\Installer;
use LPG\Commerce\Settings\Settings;
use LPG\Commerce\Settings\SettingsTab;

/**
 * Tests for LPG\Commerce\Settings\Settings and SettingsTab (§13.1).
 * Only obviously fake contact values are used.
 */
class SettingsTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// DB rollback between tests fires no option hooks, so drop the cache.
		Settings::flush();
		$_POST    = array();
		$_REQUEST = array();
	}

	public function tear_down(): void {
		$_POST    = array();
		$_REQUEST = array();
		Settings::flush();
		parent::tear_down();
	}

	private function as_role( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	private function post( array $values ): void {
		$nonce    = wp_create_nonce( 'woocommerce-settings' );
		$_POST    = array(
			'_wpnonce'                 => $nonce,
			'save'                     => 'Save changes',
			Installer::SETTINGS_OPTION => $values,
		);
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test fixture.
	}

	public function test_defaults_apply_when_nothing_is_stored(): void {
		delete_option( Installer::SETTINGS_OPTION );

		$this->assertSame( '', Settings::get( 'whatsapp_number' ) );
		$this->assertSame( '[Shop address, Nairobi]', Settings::get( 'shop_address' ) );
		$this->assertSame( '[Opening hours]', Settings::get( 'opening_hours' ) );
		$this->assertSame( "Hello, I'm interested in {product} ({sku}). {url}", Settings::get( 'wa_template_product' ) );
		$this->assertSame( "Hello, I'd like a cylinder {type}: {brand} {size}. Delivery: {location}.", Settings::get( 'wa_template_exchange' ) );
		$this->assertSame( 0, Settings::get( 'enquiry_retention_months' ) );
		$this->assertFalse( Settings::get( 'delete_data_on_uninstall' ) );
		$this->assertNull( Settings::get( 'no_such_key' ) );
	}

	public function test_default_settings_cover_every_documented_key(): void {
		$keys = array( 'whatsapp_number', 'contact_phone', 'notify_sales_email', 'notify_owner_email', 'public_email', 'shop_address', 'opening_hours', 'wa_template_product', 'wa_template_exchange', 'privacy_policy_version', 'enquiry_retention_months', 'delete_data_on_uninstall' );
		$this->assertSame( $keys, array_keys( Installer::default_settings() ) );
		$this->assertSame( '', Installer::default_settings()['notify_sales_email'] );
	}

	public function test_get_merges_stored_values_and_normalises_types(): void {
		update_option(
			Installer::SETTINGS_OPTION,
			array(
				'whatsapp_number'    => 254700000000,
				'notify_sales_email' => 'sales@example.test',
				'custom'             => 'kept',
			),
			false
		);

		$this->assertSame( '254700000000', Settings::get( 'whatsapp_number' ) );
		$this->assertSame( 'sales@example.test', Settings::get( 'notify_sales_email' ) );
		$this->assertSame( '[Opening hours]', Settings::get( 'opening_hours' ) );
		$this->assertSame( 'kept', Settings::get( 'custom' ) );
	}

	public function test_cache_is_invalidated_when_the_option_changes(): void {
		update_option( Installer::SETTINGS_OPTION, array( 'contact_phone' => '254700000000' ), false );
		$this->assertSame( '254700000000', Settings::get( 'contact_phone' ) );

		update_option( Installer::SETTINGS_OPTION, array( 'contact_phone' => '254711111111' ), false );
		$this->assertSame( '254711111111', Settings::get( 'contact_phone' ) );

		delete_option( Installer::SETTINGS_OPTION );
		$this->assertSame( '', Settings::get( 'contact_phone' ) );
	}

	public function test_tab_is_registered(): void {
		$tabs = apply_filters( 'woocommerce_settings_tabs_array', array() );
		$this->assertArrayHasKey( SettingsTab::TAB, $tabs );
		$this->assertTrue( has_action( 'woocommerce_update_options_' . SettingsTab::TAB ) > 0 );
	}

	public function test_save_stores_valid_values_without_autoload_and_keeps_unmanaged_keys(): void {
		update_option( Installer::SETTINGS_OPTION, array( 'unmanaged' => 'keep-me' ), false );
		$this->as_role( 'shop_manager' );
		$this->post(
			array(
				'whatsapp_number'          => '+254 700-000 000',
				'contact_phone'            => '254700000000',
				'notify_sales_email'       => 'sales@example.test',
				'notify_owner_email'       => 'owner@example.test',
				'shop_address'             => "<b>1 Test Road</b>\nNairobi",
				'opening_hours'            => 'Mon-Sat 8-6',
				'wa_template_product'      => 'Hi {product}',
				'wa_template_exchange'     => '',
				'privacy_policy_version'   => 'v2',
				'enquiry_retention_months' => '12',
				'delete_data_on_uninstall' => '1',
			)
		);

		$errors = ( new SettingsTab() )->handle_save();

		$this->assertSame( array(), $errors );
		$stored = get_option( Installer::SETTINGS_OPTION );
		$this->assertSame( 'keep-me', $stored['unmanaged'] );
		$this->assertSame( '254700000000', $stored['whatsapp_number'] );
		$this->assertSame( 'sales@example.test', $stored['notify_sales_email'] );
		$this->assertSame( "1 Test Road\nNairobi", $stored['shop_address'] );
		$this->assertSame( 'Hi {product}', $stored['wa_template_product'] );
		$this->assertSame( Installer::default_settings()['wa_template_exchange'], $stored['wa_template_exchange'], 'An empty template restores the default.' );
		$this->assertSame( 12, $stored['enquiry_retention_months'] );
		$this->assertTrue( $stored['delete_data_on_uninstall'] );
		$this->assertArrayNotHasKey( Installer::SETTINGS_OPTION, wp_load_alloptions() );
		$this->assertSame( 'sales@example.test', Settings::get( 'notify_sales_email' ) );
	}

	public function test_invalid_number_and_email_are_rejected_and_old_values_kept(): void {
		update_option(
			Installer::SETTINGS_OPTION,
			array(
				'whatsapp_number'          => '254700000000',
				'contact_phone'            => '254700000000',
				'notify_owner_email'       => 'owner@example.test',
				'enquiry_retention_months' => 24,
				'delete_data_on_uninstall' => true,
			),
			false
		);
		$this->as_role( 'administrator' );
		$this->post(
			array(
				'whatsapp_number'          => '0700000000',
				'contact_phone'            => '2547abc',
				'notify_sales_email'       => 'sales@example.test',
				'notify_owner_email'       => 'not-an-email',
				'enquiry_retention_months' => '-3',
			)
		);

		$errors = ( new SettingsTab() )->handle_save();

		$this->assertCount( 4, $errors );
		$stored = get_option( Installer::SETTINGS_OPTION );
		$this->assertSame( '254700000000', $stored['whatsapp_number'] );
		$this->assertSame( '254700000000', $stored['contact_phone'] );
		$this->assertSame( 'owner@example.test', $stored['notify_owner_email'] );
		$this->assertSame( 24, $stored['enquiry_retention_months'] );
		$this->assertSame( 'sales@example.test', $stored['notify_sales_email'], 'Valid fields still save.' );
		$this->assertFalse( $stored['delete_data_on_uninstall'], 'An unticked checkbox saves false.' );
	}

	public function test_numbers_and_emails_can_be_cleared(): void {
		update_option( Installer::SETTINGS_OPTION, array( 'whatsapp_number' => '254700000000' ), false );
		$this->as_role( 'administrator' );
		$this->post(
			array(
				'whatsapp_number'    => '',
				'notify_sales_email' => '',
			)
		);

		$this->assertSame( array(), ( new SettingsTab() )->handle_save() );
		$this->assertSame( '', Settings::get( 'whatsapp_number' ) );
	}

	public function test_save_requires_capability_and_nonce(): void {
		update_option( Installer::SETTINGS_OPTION, array( 'whatsapp_number' => '254700000000' ), false );

		$this->as_role( 'subscriber' );
		$this->post( array( 'whatsapp_number' => '254711111111' ) );
		( new SettingsTab() )->handle_save();
		$this->assertSame( '254700000000', get_option( Installer::SETTINGS_OPTION )['whatsapp_number'] );

		$this->as_role( 'administrator' );
		$this->post( array( 'whatsapp_number' => '254711111111' ) );
		$_POST['_wpnonce'] = 'bad';
		( new SettingsTab() )->handle_save();
		$this->assertSame( '254700000000', get_option( Installer::SETTINGS_OPTION )['whatsapp_number'] );
	}

	public function test_woocommerce_settings_flow_saves_and_reports_errors(): void {
		global $current_tab;
		update_option( Installer::SETTINGS_OPTION, array( 'notify_sales_email' => 'sales@example.test' ), false );
		$this->as_role( 'administrator' );
		$this->post(
			array(
				'whatsapp_number'    => '254700000000',
				'notify_sales_email' => 'bad@@example',
			)
		);
		$current_tab = SettingsTab::TAB; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		WC_Admin_Settings::save();

		ob_start();
		WC_Admin_Settings::show_messages();
		$messages = ob_get_clean();

		$this->assertSame( '254700000000', Settings::get( 'whatsapp_number' ) );
		$this->assertSame( 'sales@example.test', Settings::get( 'notify_sales_email' ) );
		$this->assertStringContainsString( 'Sales notification email was not saved', $messages );
	}

	public function test_render_outputs_fields_with_stored_values(): void {
		update_option(
			Installer::SETTINGS_OPTION,
			array(
				'whatsapp_number'          => 254700000000,
				'delete_data_on_uninstall' => true,
			),
			false
		);
		$this->as_role( 'administrator' );

		ob_start();
		( new SettingsTab() )->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="lpg_settings[whatsapp_number]"', $html );
		$this->assertStringContainsString( 'value="254700000000"', $html );
		$this->assertStringContainsString( 'name="lpg_settings[notify_owner_email]"', $html );
		$this->assertStringContainsString( '[Shop address, Nairobi]', $html );
		$this->assertMatchesRegularExpression( '/name="lpg_settings\[delete_data_on_uninstall\]"[^>]*checked/s', $html );

		$this->as_role( 'subscriber' );
		ob_start();
		( new SettingsTab() )->render();
		$this->assertSame( '', ob_get_clean() );
	}
}
