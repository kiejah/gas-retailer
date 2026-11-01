<?php
/**
 * Shared fixtures for quote request / enquiry tests.
 *
 * Only obviously fake contact data (example.test, 2547000000xx).
 *
 * @package LPG\Commerce\Tests
 */

use LPG\Commerce\Installer;
use LPG\Commerce\Settings\Settings;

/**
 * Fixtures: REST server, requests with a real `wp_rest` nonce, products, settings.
 */
trait LpgRecordFixtures {

	/**
	 * Fresh REST server and fake settings. Call from set_up().
	 */
	protected function boot_records(): void {
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		do_action( 'rest_api_init', $wp_rest_server );

		$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
		wp_set_current_user( 0 );
		reset_phpmailer_instance();
		$this->set_settings( array() );
	}

	/**
	 * Undo boot_records(). Call from tear_down().
	 */
	protected function shutdown_records(): void {
		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		unset( $_SERVER['REMOTE_ADDR'] );
		reset_phpmailer_instance();
		Settings::flush();
	}

	/**
	 * Store fake settings (merged over fake defaults).
	 *
	 * @param array<string, mixed> $overrides Overrides.
	 */
	protected function set_settings( array $overrides ): void {
		update_option(
			Installer::SETTINGS_OPTION,
			array_merge(
				Installer::default_settings(),
				array(
					'whatsapp_number'    => '254700000001',
					'contact_phone'      => '254700000001',
					'notify_sales_email' => 'sales@example.test',
					'notify_owner_email' => 'owner@example.test',
				),
				$overrides
			),
			false
		);
		Settings::flush();
	}

	/**
	 * POST to an lpg/v1 route as the current user, with a valid nonce by default.
	 *
	 * @param string               $route Route, e.g. '/rfq'.
	 * @param array<string, mixed> $body  JSON body.
	 * @param string|null|false    $nonce Nonce; null = valid one, false = none.
	 */
	protected function post_route( string $route, array $body, $nonce = null ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/lpg/v1' . $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
		if ( false !== $nonce ) {
			$request->set_header( 'X-WP-Nonce', null === $nonce ? wp_create_nonce( 'wp_rest' ) : $nonce );
		}
		return rest_do_request( $request );
	}

	/**
	 * A `[QA TEST]` simple product.
	 *
	 * @param string $status Post status.
	 * @param string $sku    SKU.
	 */
	protected function product( string $status = 'publish', string $sku = '' ): int {
		$product = new WC_Product_Simple();
		$product->set_name( '[QA TEST] Regulator & Hose' );
		$product->set_status( $status );
		if ( '' !== $sku ) {
			$product->set_sku( $sku );
		}
		return $product->save();
	}

	/**
	 * A valid RFQ body.
	 *
	 * @param array<string, mixed> $overrides Top-level overrides.
	 * @return array<string, mixed>
	 */
	protected function rfq_body( array $overrides = array() ): array {
		return array_merge(
			array(
				'contact'      => array(
					'name'         => 'Test Buyer',
					'organisation' => 'Example Hotel Ltd',
					'phone'        => '0700 000 002',
					'email'        => 'buyer@example.test',
				),
				'segment'      => 'commercial',
				'items'        => array(
					array(
						'description' => '13kg cylinders',
						'qty'         => 10,
					),
				),
				'requirements' => 'Kitchen install',
				'project'      => 'Hotel kitchen',
				'location'     => 'Westlands',
				'comments'     => "Call after 2pm.\nThanks",
				'consent'      => true,
				'hp'           => '',
			),
			$overrides
		);
	}

	/**
	 * Number of records of a type, any status.
	 *
	 * @param string $post_type Post type.
	 */
	protected function count_records( string $post_type ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", $post_type ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
