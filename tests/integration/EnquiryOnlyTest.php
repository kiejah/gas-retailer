<?php
/**
 * Enquiry-only purchasable rule and product fields (§11.5, C6, C7).
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgFixtures.php';

use LPG\Commerce\Commerce\EnquiryOnly;
use LPG\Commerce\Commerce\ProductFields;

class EnquiryOnlyTest extends WP_UnitTestCase {

	use LpgFixtures;

	public function test_normal_priced_product_is_purchasable(): void {
		$product = $this->create_product();

		$this->assertFalse( EnquiryOnly::is_enquiry_only( $product ) );
		$this->assertTrue( $product->is_purchasable() );
	}

	public function test_flagged_product_is_not_purchasable_and_price_unchanged(): void {
		$product = $this->create_product( array(), array( '_lpg_enquiry_only' => 'yes' ), '2500' );
		$product = wc_get_product( $product->get_id() );

		$this->assertTrue( EnquiryOnly::is_enquiry_only( $product ) );
		$this->assertFalse( $product->is_purchasable() );
		$this->assertSame( '2500', $product->get_regular_price() );
	}

	public function test_empty_price_is_enquiry_only(): void {
		$product = $this->create_product( array(), array(), '' );

		$this->assertTrue( EnquiryOnly::is_enquiry_only( $product ) );
		$this->assertFalse( $product->is_purchasable() );
	}

	public function test_zero_price_is_not_empty(): void {
		$product = $this->create_product( array(), array(), '0' );

		$this->assertFalse( EnquiryOnly::is_enquiry_only( $product ) );
		$this->assertTrue( $product->is_purchasable() );
	}

	public function test_filter_can_override(): void {
		$product = $this->create_product();
		add_filter( 'lpg_is_enquiry_only', '__return_true' );

		$this->assertTrue( EnquiryOnly::is_enquiry_only( $product ) );
		$this->assertFalse( $product->is_purchasable() );
	}

	public function test_parent_flag_applies_to_variations(): void {
		$attribute = new WC_Product_Attribute();
		$attribute->set_name( 'Size' );
		$attribute->set_options( array( 'Small', 'Large' ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$parent = new WC_Product_Variable();
		$parent->set_name( '[DEMO] Variable' );
		$parent->set_status( 'publish' );
		$parent->set_attributes( array( $attribute ) );
		$parent->save();

		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_attributes( array( 'size' => 'Small' ) );
		$variation->set_regular_price( '500' );
		$variation->save();
		$variation = wc_get_product( $variation->get_id() );

		$this->assertTrue( $variation->is_purchasable(), 'Unflagged variation is purchasable.' );

		update_post_meta( $parent->get_id(), '_lpg_enquiry_only', 'yes' );
		$variation = wc_get_product( $variation->get_id() );

		$this->assertTrue( EnquiryOnly::is_enquiry_only( $variation ) );
		$this->assertFalse( $variation->is_purchasable() );
	}

	public function test_product_fields_apply_and_hook(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$product = $this->create_product();

		$_POST = array(
			'lpg_product_fields_nonce' => wp_create_nonce( 'lpg_save_product_fields' ),
			'_lpg_warranty'            => "12 months\n<script>x</script>parts",
			'_lpg_enquiry_only'        => 'yes',
		);
		do_action( 'woocommerce_admin_process_product_object', $product );
		$product->save();
		$_POST = array();

		$this->assertSame( "12 months\nparts", get_post_meta( $product->get_id(), '_lpg_warranty', true ) );
		$this->assertSame( 'yes', get_post_meta( $product->get_id(), '_lpg_enquiry_only', true ) );

		ProductFields::apply( $product, array() );
		$product->save();
		$this->assertFalse( metadata_exists( 'post', $product->get_id(), '_lpg_warranty' ) );
		$this->assertSame( 'no', get_post_meta( $product->get_id(), '_lpg_enquiry_only', true ) );
	}
}
