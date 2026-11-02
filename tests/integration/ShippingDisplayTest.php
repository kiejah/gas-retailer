<?php
/**
 * Shipping display tweaks (C23, DEF-011).
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgRecordFixtures.php';

use LPG\Commerce\Commerce\ShippingDisplay;

/**
 * Tests for LPG\Commerce\Commerce\ShippingDisplay.
 */
class ShippingDisplayTest extends WP_UnitTestCase {

	use LpgRecordFixtures;

	/**
	 * Customer object in place before the test.
	 *
	 * @var WC_Customer|null
	 */
	private $previous_customer;

	public function set_up(): void {
		parent::set_up();
		// Registered here until the Lead adds it to Plugin::SERVICES (same callables, so no duplicates).
		( new ShippingDisplay() )->register();
		$this->set_settings( array() );
		$this->previous_customer = WC()->customer;
		WC()->customer           = new WC_Customer();
	}

	public function tear_down(): void {
		WC()->customer = $this->previous_customer;
		$this->shutdown_records();
		parent::tear_down();
	}

	private function rate( string $method, int $instance, string $cost ): WC_Shipping_Rate {
		return new WC_Shipping_Rate( "{$method}:{$instance}", $method, $cost, array(), $method, $instance );
	}

	public function test_flat_rate_hidden_when_free_delivery_applies(): void {
		$rates = array(
			'flat_rate:1'     => $this->rate( 'flat_rate', 1, '300' ),
			'free_shipping:2' => $this->rate( 'free_shipping', 2, '0' ),
			'local_pickup:3'  => $this->rate( 'local_pickup', 3, '0' ),
		);

		$filtered = apply_filters( 'woocommerce_package_rates', $rates, array() );

		$this->assertSame( array( 'free_shipping:2', 'local_pickup:3' ), array_keys( $filtered ) );
	}

	public function test_rates_unchanged_without_free_delivery(): void {
		$rates = array(
			'flat_rate:1'    => $this->rate( 'flat_rate', 1, '300' ),
			'local_pickup:3' => $this->rate( 'local_pickup', 3, '0' ),
		);
		$this->assertSame( $rates, apply_filters( 'woocommerce_package_rates', $rates, array() ) );
		$this->assertSame( array(), ShippingDisplay::hide_paid_when_free( array() ) );
	}

	public function test_kenyan_address_without_rates_gets_whatsapp_note(): void {
		WC()->customer->set_shipping_country( 'KE' );
		WC()->customer->set_shipping_state( 'KE28' );

		foreach ( array( 'woocommerce_no_shipping_available_html', 'woocommerce_cart_no_shipping_available_html' ) as $filter ) {
			$html = apply_filters( $filter, 'Default text' );
			$this->assertStringContainsString( 'We currently deliver within Nairobi only. For other locations, contact us on WhatsApp.', $html, $filter );
			$this->assertStringContainsString( 'href="https://wa.me/254700000001?text=Hello%2C%20I%20would%20like%20a%20delivery%20outside%20Nairobi."', $html, $filter );
			$this->assertStringContainsString( '<span class="screen-reader-text"> (opens in a new tab)</span>', $html );
			$this->assertSame( $html, wp_kses_post( $html ), 'Survives the template\'s wp_kses_post.' );
		}
	}

	public function test_note_without_number_has_no_link(): void {
		WC()->customer->set_shipping_country( 'KE' );
		$this->set_settings( array( 'whatsapp_number' => '' ) );

		$html = apply_filters( 'woocommerce_no_shipping_available_html', 'Default text' );
		$this->assertStringContainsString( 'Nairobi only', $html );
		$this->assertStringNotContainsString( '<a ', $html );
	}

	public function test_other_countries_keep_woocommerce_text(): void {
		WC()->customer->set_shipping_country( 'UG' );
		$this->assertSame( 'Default text', apply_filters( 'woocommerce_no_shipping_available_html', 'Default text' ) );
	}
}
