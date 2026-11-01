<?php
/**
 * Enquiry label and "View details" card button (§12.1, P-015, C6, C7).
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgFixtures.php';

use LPG\Commerce\Commerce\EnquiryDisplay;

class EnquiryDisplayTest extends WP_UnitTestCase {

	use LpgFixtures;

	private const LABEL = '<span class="lpg-enquire-label">Enquire</span>';

	public function set_up(): void {
		parent::set_up();
		// Plugin::SERVICES registration is added by the Lead; hooks are restored after each test.
		( new EnquiryDisplay() )->register();
		set_current_screen( 'front' );
	}

	public function tear_down(): void {
		unset( $_SERVER['HTTP_REFERER'] );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	private function expected_link( WC_Product $product ): string {
		return '<a class="lpg-enquire-link wp-element-button is-style-outline" href="' . esc_url( get_permalink( $product->get_id() ) ) . '">View details</a>';
	}

	private function flagged( string $price = '2500' ): WC_Product {
		return wc_get_product( $this->create_product( array(), array( '_lpg_enquiry_only' => 'yes' ), $price )->get_id() );
	}

	/**
	 * Render a block through render_block() with the given postId as context.
	 */
	private function render_with_post_id( string $markup, int $post_id ): string {
		$inject = static function ( $context ) use ( $post_id ) {
			$context['postId']   = $post_id;
			$context['postType'] = 'product';
			return $context;
		};
		add_filter( 'render_block_context', $inject );
		$html = render_block( parse_blocks( $markup )[0] );
		remove_filter( 'render_block_context', $inject );

		return $html;
	}

	/* ---------- Price HTML ---------- */

	public function test_flagged_product_shows_label(): void {
		$this->assertSame( self::LABEL, $this->flagged()->get_price_html() );
	}

	public function test_empty_price_product_shows_label(): void {
		$product = wc_get_product( $this->create_product( array(), array(), '' )->get_id() );

		$this->assertSame( self::LABEL, $product->get_price_html() );
	}

	public function test_zero_price_product_shows_normal_price(): void {
		$product = wc_get_product( $this->create_product( array(), array(), '0' )->get_id() );
		$html    = $product->get_price_html();

		$this->assertStringNotContainsString( 'lpg-enquire-label', $html );
		$this->assertStringContainsString( 'woocommerce-Price-amount', $html );
	}

	public function test_normal_product_shows_normal_price(): void {
		$product = wc_get_product( $this->create_product( array(), array(), '1500' )->get_id() );
		$html    = $product->get_price_html();

		$this->assertStringNotContainsString( 'lpg-enquire-label', $html );
		$this->assertStringContainsString( '1,500', $html );
	}

	public function test_admin_price_html_unchanged(): void {
		$product = $this->flagged();
		set_current_screen( 'edit-product' );

		$this->assertTrue( is_admin() );
		$html = $product->get_price_html();
		$this->assertStringNotContainsString( 'lpg-enquire-label', $html );
		$this->assertStringContainsString( '2,500', $html );
	}

	public function test_admin_ajax_from_admin_screen_unchanged_but_from_front_end_labelled(): void {
		$product = $this->flagged();
		set_current_screen( 'edit-product' );
		add_filter( 'wp_doing_ajax', '__return_true' );

		// E.g. quick edit in the product list re-renders the row via admin-ajax.
		$_SERVER['HTTP_REFERER'] = admin_url( 'edit.php?post_type=product' );
		$this->assertStringNotContainsString( 'lpg-enquire-label', $product->get_price_html() );

		$_SERVER['HTTP_REFERER'] = home_url( '/shop/' );
		$this->assertSame( self::LABEL, $product->get_price_html() );
	}

	public function test_rest_request_gets_price(): void {
		$product = $this->flagged();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wc/v3/products/' . $product->get_id() ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '2500', $data['regular_price'] );
		$this->assertStringNotContainsString( 'lpg-enquire-label', $data['price_html'] );
		$this->assertStringContainsString( '2,500', $data['price_html'] );

		// Outside the request, the storefront label is back (the depth counter was reset).
		$this->assertSame( self::LABEL, $product->get_price_html() );
	}

	/**
	 * Recorded behaviour (§12.1): the Store API still exposes the stored price.
	 */
	public function test_store_api_keeps_price(): void {
		$product  = $this->flagged();
		$response = rest_do_request( new WP_REST_Request( 'GET', '/wc/store/v1/products/' . $product->get_id() ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$prices = (array) $data['prices'];
		$this->assertSame( (string) ( 2500 * ( 10 ** $prices['currency_minor_unit'] ) ), $prices['regular_price'] );
		$this->assertSame( $prices['regular_price'], $prices['price'] );
		$this->assertStringNotContainsString( 'lpg-enquire-label', $data['price_html'] );
		$this->assertFalse( $data['is_purchasable'] );
	}

	/**
	 * Recorded behaviour (§12.1, W-012): product JSON-LD `offers` keeps the stored price.
	 */
	public function test_json_ld_offers_keep_price(): void {
		$product = $this->flagged();
		$sd      = new WC_Structured_Data();
		$sd->generate_product_data( $product );

		$markup = null;
		foreach ( $sd->get_data() as $item ) {
			if ( isset( $item['@type'] ) && 'Product' === $item['@type'] ) {
				$markup = $item;
			}
		}

		$this->assertNotNull( $markup );
		$this->assertNotEmpty( $markup['offers'] );
		$offer = $markup['offers'][0];
		$this->assertSame( '2500', (string) (float) $offer['priceSpecification'][0]['price'] );
		$this->assertStringNotContainsString( 'Enquire', wp_json_encode( $markup ) );
	}

	public function test_variation_of_flagged_parent_shows_label(): void {
		$attribute = new WC_Product_Attribute();
		$attribute->set_name( 'Size' );
		$attribute->set_options( array( 'Small', 'Large' ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$parent = new WC_Product_Variable();
		$parent->set_name( '[DEMO] Variable' );
		$parent->set_status( 'publish' );
		$parent->set_attributes( array( $attribute ) );
		$parent->update_meta_data( '_lpg_enquiry_only', 'yes' );
		$parent->save();

		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_attributes( array( 'size' => 'Small' ) );
		$variation->set_regular_price( '500' );
		$variation->save();
		WC_Product_Variable::sync( $parent->get_id() );

		$this->assertSame( self::LABEL, wc_get_product( $variation->get_id() )->get_price_html() );
		$this->assertSame( self::LABEL, wc_get_product( $parent->get_id() )->get_price_html() );
		$this->assertSame( '500', get_post_meta( $variation->get_id(), '_regular_price', true ) );
	}

	public function test_stored_price_meta_unchanged_after_rendering(): void {
		$product = $this->flagged();
		$id      = $product->get_id();

		$product->get_price_html();
		apply_filters( 'woocommerce_loop_add_to_cart_link', '<a>x</a>', $product, array() );
		$this->render_with_post_id( '<!-- wp:woocommerce/product-price /-->', $id );
		$this->render_with_post_id( '<!-- wp:woocommerce/product-button /-->', $id );

		wp_cache_flush();
		$this->assertSame( '2500', get_post_meta( $id, '_price', true ) );
		$this->assertSame( '2500', get_post_meta( $id, '_regular_price', true ) );
		$this->assertSame( '2500', wc_get_product( $id )->get_price( 'edit' ) );
	}

	/* ---------- Card button ---------- */

	public function test_classic_loop_link_for_enquiry_only_product(): void {
		global $product;
		$product = $this->flagged();

		ob_start();
		woocommerce_template_loop_add_to_cart();
		$html = ob_get_clean();

		$this->assertStringContainsString( $this->expected_link( $product ), $html );
		$this->assertStringNotContainsString( 'add_to_cart_button', $html );
		$product = null;
	}

	public function test_classic_loop_link_for_normal_product_unchanged(): void {
		$product  = $this->create_product();
		$original = '<a href="?add-to-cart=1" class="button">Add to cart</a>';

		$this->assertSame( $original, apply_filters( 'woocommerce_loop_add_to_cart_link', $original, $product, array() ) );
	}

	public function test_product_button_block_for_enquiry_only_product(): void {
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'woocommerce/product-button' ) );
		$product = $this->flagged();

		$html = $this->render_with_post_id( '<!-- wp:woocommerce/product-button /-->', $product->get_id() );

		$this->assertSame( $this->expected_link( $product ), $html );
	}

	public function test_product_button_block_for_normal_product_unchanged(): void {
		$product = $this->create_product();

		$html = $this->render_with_post_id( '<!-- wp:woocommerce/product-button /-->', $product->get_id() );

		$this->assertStringNotContainsString( 'lpg-enquire-link', $html );
		$this->assertStringContainsString( 'add_to_cart_button', $html );
		$this->assertStringContainsString( 'Add to cart', $html );
	}

	public function test_product_price_block_shows_label(): void {
		$product = $this->flagged();

		$html = $this->render_with_post_id( '<!-- wp:woocommerce/product-price /-->', $product->get_id() );

		$this->assertStringContainsString( self::LABEL, $html );
		$this->assertStringNotContainsString( '2,500', $html );
	}

	public function test_link_escapes_name_and_permalink(): void {
		$product = $this->flagged();
		$product->set_name( '<script>alert(1)</script>"><b>Bold</b>' );
		$product->save();

		$evil = static function () {
			return 'http://example.org/?p=1&x="><script>alert(2)</script>';
		};
		add_filter( 'post_type_link', $evil );
		$html = EnquiryDisplay::link_html( $product );
		remove_filter( 'post_type_link', $evil );

		$this->assertStringStartsWith( '<a class="lpg-enquire-link wp-element-button is-style-outline" href="http://example.org/?p=1&#038;x=scriptalert(2)/script">View details</a>', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertSame( 1, substr_count( $html, '<a ' ) );
		$this->assertSame( 1, substr_count( $html, '</a>' ) );
	}
}
