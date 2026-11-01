<?php
/**
 * CAT-097: a product already in the cart becomes enquiry-only.
 *
 * WooCommerce 11.2 behaviour (read from its code):
 * - WC_Cart_Session::get_cart_from_session() (every request, on wp_loaded)
 *   drops items whose is_purchasable() is false and adds an error notice
 *   "... has been removed from your cart because it can no longer be purchased".
 * - The Store API checkout validates every cart item with is_purchasable()
 *   (CartController::validate_cart_items) and refuses with
 *   woocommerce_rest_product_not_purchasable.
 * - WC_Cart::check_cart_items() does NOT check purchasability (only existence,
 *   sold-individually and stock), so it is not what removes the item.
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgFixtures.php';

use Automattic\WooCommerce\StoreApi\Exceptions\InvalidCartException;
use Automattic\WooCommerce\StoreApi\Utilities\CartController;
use LPG\Commerce\Commerce\ProductFields;

class CartEnquiryOnlyTest extends WP_UnitTestCase {

	use LpgFixtures;

	public function set_up(): void {
		parent::set_up();
		add_filter( 'woocommerce_set_cookie_enabled', '__return_false' );
		wp_set_current_user( 0 );
		wc_load_cart();
		WC()->cart->empty_cart();
		wc_clear_notices();
	}

	public function tear_down(): void {
		WC()->cart->empty_cart();
		wc_clear_notices();
		parent::tear_down();
	}

	/**
	 * Put a purchasable product in the cart and persist it to the session.
	 */
	private function product_in_cart(): array {
		$product = $this->create_product( array(), array(), '1000' );
		$this->assertTrue( $product->is_purchasable(), 'Precondition: purchasable.' );

		$key = WC()->cart->add_to_cart( $product->get_id(), 2 );
		$this->assertIsString( $key, 'Added to cart.' );
		WC()->cart->calculate_totals();
		WC()->cart->set_session();
		$this->assertArrayHasKey( $key, (array) WC()->session->get( 'cart' ), 'Persisted to the session.' );
		$this->assertTrue( WC()->cart->check_cart_items() );

		return array( $product, $key );
	}

	/**
	 * Simulate the next request: a fresh cart object loaded from the session.
	 */
	private function next_request_cart(): WC_Cart {
		WC()->cart = new WC_Cart();
		WC()->cart->get_cart_from_session();

		return WC()->cart;
	}

	private function error_notices(): string {
		return implode( ' ', wp_list_pluck( wc_get_notices( 'error' ), 'notice' ) );
	}

	public function test_item_flagged_enquiry_only_is_removed_when_cart_reloads(): void {
		[ $product, $key ] = $this->product_in_cart();

		// Flag it through the plugin's field handler, as the admin save does.
		ProductFields::apply( $product, array( 'enquiry_only' => true ) );
		$product->save();
		$this->assertFalse( wc_get_product( $product->get_id() )->is_purchasable() );

		$cart = $this->next_request_cart();

		$this->assertTrue( $cart->is_empty(), 'WooCommerce removed the item.' );
		$this->assertArrayNotHasKey( $key, $cart->get_cart() );
		$this->assertStringContainsString( 'has been removed from your cart because it can no longer be purchased', $this->error_notices() );
		$this->assertStringContainsString( $product->get_name(), $this->error_notices() );
		$this->assertArrayNotHasKey( $key, (array) WC()->session->get( 'cart' ), 'Session cart updated too.' );
		$this->assertSame( '1000', get_post_meta( $product->get_id(), '_regular_price', true ), 'Price unchanged.' );
	}

	public function test_item_whose_price_is_cleared_is_removed_when_cart_reloads(): void {
		[ $product, $key ] = $this->product_in_cart();

		$product->set_regular_price( '' );
		$product->save();

		$cart = $this->next_request_cart();

		$this->assertTrue( $cart->is_empty() );
		$this->assertArrayNotHasKey( $key, $cart->get_cart() );
		$this->assertStringContainsString( 'can no longer be purchased', $this->error_notices() );
	}

	public function test_store_api_checkout_validation_blocks_the_stale_cart_in_the_same_request(): void {
		[ $product ] = $this->product_in_cart();

		ProductFields::apply( $product, array( 'enquiry_only' => true ) );
		$product->save();

		// The in-memory cart still holds the item (no reload yet).
		$this->assertCount( 1, WC()->cart->get_cart() );
		// Classic check_cart_items() does not look at purchasability (WooCommerce behaviour).
		$this->assertTrue( WC()->cart->check_cart_items(), 'check_cart_items() does not check is_purchasable().' );

		try {
			( new CartController() )->validate_cart_items();
			$this->fail( 'Store API cart validation accepted an enquiry-only item.' );
		} catch ( InvalidCartException $e ) {
			$this->assertSame( 'woocommerce_stock_availability_error', $e->getErrorCode() );
			$this->assertContains( 'woocommerce_rest_product_not_purchasable', $e->getError()->get_error_codes() );
		}
	}

	public function test_unflagged_item_survives_reload(): void {
		[ , $key ] = $this->product_in_cart();

		$cart = $this->next_request_cart();

		$this->assertArrayHasKey( $key, $cart->get_cart(), 'Control: a purchasable item stays.' );
		$this->assertSame( '', $this->error_notices() );
	}
}
