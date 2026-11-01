<?php
/**
 * `lpg/product-warranty` block (§12.4).
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgFixtures.php';
require_once ABSPATH . WPINC . '/class-phpass.php';

use LPG\Commerce\Blocks\ProductWarranty;
use LPG\Commerce\Blocks\Registry;
use LPG\Commerce\Commerce\ProductFields;

class WarrantyBlockTest extends WP_UnitTestCase {

	use LpgFixtures;

	private const ALL_BLOCKS = array( 'lpg/spec-table', 'lpg/compatible-products', 'lpg/product-documents', 'lpg/product-warranty' );

	public function tear_down(): void {
		unset( $GLOBALS['product'], $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );
		parent::tear_down();
	}

	private function product_with_warranty( string $warranty = '12 months' ): WC_Product_Simple {
		return $this->create_product( array(), array( ProductFields::WARRANTY_META => $warranty ) );
	}

	// ----- Nothing to render -------------------------------------------------------

	public function test_empty_without_a_product(): void {
		$post = self::factory()->post->create();

		$this->assertSame( '', ProductWarranty::render( array(), null ) );
		$this->assertSame( '', ProductWarranty::render( array(), 0 ) );
		$this->assertSame( '', ProductWarranty::render( array(), $post ), 'Non-product post.' );
		$this->assertSame( '', ProductWarranty::render( array(), 999999 ), 'Missing ID.' );
	}

	public function test_empty_without_a_warranty(): void {
		$this->assertSame( '', ProductWarranty::render( array(), $this->create_product()->get_id() ) );

		$blank = $this->create_product();
		update_post_meta( $blank->get_id(), ProductFields::WARRANTY_META, "  \n\t " );
		$this->assertSame( '', ProductWarranty::render( array(), $blank->get_id() ), 'Whitespace only.' );

		$array = $this->create_product();
		update_post_meta( $array->get_id(), ProductFields::WARRANTY_META, array( 'x' ) );
		$this->assertSame( '', ProductWarranty::render( array(), $array->get_id() ), 'Non-scalar meta.' );
	}

	public function test_password_protected_product_renders_nothing_until_password_is_entered(): void {
		$product = $this->product_with_warranty();
		wp_update_post(
			array(
				'ID'            => $product->get_id(),
				'post_password' => 'secret',
			)
		);

		$this->assertSame( '', ProductWarranty::render( array(), $product->get_id() ) );

		$GLOBALS['product'] = wc_get_product( $product->get_id() );
		$this->assertSame( '', ProductWarranty::render( array(), null ), 'Global product fallback is protected too.' );

		$hasher                                 = new PasswordHash( 8, true );
		$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = $hasher->HashPassword( 'secret' );
		$this->assertStringContainsString( '12 months', ProductWarranty::render( array(), $product->get_id() ) );
	}

	public function test_draft_product_renders_nothing_for_guests(): void {
		$product = $this->product_with_warranty();
		$product->set_status( 'draft' );
		$product->save();

		wp_set_current_user( 0 );
		$this->assertSame( '', ProductWarranty::render( array(), $product->get_id() ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertStringContainsString( '12 months', ProductWarranty::render( array(), $product->get_id() ) );
	}

	// ----- Markup ----------------------------------------------------------------------

	public function test_markup_with_default_label(): void {
		$product = $this->product_with_warranty( ' 12 months ' );

		$expected = '<p class="lpg-product-warranty"><span class="lpg-product-warranty__label">Warranty:</span> <span class="lpg-product-warranty__value">12 months</span></p>';
		$this->assertSame( $expected, ProductWarranty::render( array(), $product->get_id() ) );
		$this->assertSame( $expected, ProductWarranty::render( array( 'label' => 'Warranty:' ), $product->get_id() ) );
	}

	public function test_global_product_fallback(): void {
		$GLOBALS['product'] = $this->product_with_warranty();

		$this->assertStringContainsString( '<span class="lpg-product-warranty__value">12 months</span>', ProductWarranty::render( array(), null ) );
	}

	public function test_custom_label_is_escaped_and_empty_label_hides_it(): void {
		$product = $this->product_with_warranty();

		$this->assertSame(
			'<p class="lpg-product-warranty"><span class="lpg-product-warranty__label">Guarantee &amp; cover</span> <span class="lpg-product-warranty__value">12 months</span></p>',
			ProductWarranty::render( array( 'label' => '  Guarantee & cover ' ), $product->get_id() )
		);
		$this->assertSame(
			'<p class="lpg-product-warranty"><span class="lpg-product-warranty__value">12 months</span></p>',
			ProductWarranty::render( array( 'label' => '' ), $product->get_id() )
		);
		$this->assertStringContainsString(
			'&lt;b&gt;x&lt;/b&gt;',
			ProductWarranty::render( array( 'label' => '<b>x</b>' ), $product->get_id() )
		);
	}

	public function test_multi_line_warranty_keeps_line_breaks(): void {
		$product = $this->product_with_warranty( "12 months parts\n6 months labour" );

		$this->assertStringContainsString(
			'<span class="lpg-product-warranty__value">12 months parts<br />' . "\n" . '6 months labour</span>',
			ProductWarranty::render( array(), $product->get_id() )
		);
	}

	public function test_hostile_raw_meta_is_escaped(): void {
		$product = $this->create_product();
		// Raw write, bypassing sanitize_textarea_field (as REST meta_data can).
		update_post_meta( $product->get_id(), ProductFields::WARRANTY_META, "<script>alert(1)</script>\n\" onmouseover=\"alert(2)\"onmouseover=x" );

		$html = ProductWarranty::render( array(), $product->get_id() );

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '"onmouseover', $html );
		$this->assertStringNotContainsString( '" onmouseover', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;<br />', $html );
		$this->assertStringContainsString( '&quot; onmouseover=&quot;alert(2)&quot;onmouseover=x', $html );
	}

	// ----- Block rendering and registration ------------------------------------------------

	public function test_render_block_applies_block_supports(): void {
		$product = $this->product_with_warranty();
		$context = static function ( array $context ) use ( $product ): array {
			$context['postId'] = $product->get_id();
			return $context;
		};
		add_filter( 'render_block_context', $context );

		$html = render_block( parse_blocks( '<!-- wp:lpg/product-warranty {"label":"Cover:","align":"wide"} /-->' )[0] );

		remove_filter( 'render_block_context', $context );

		$this->assertMatchesRegularExpression( '#^<p class="[^"]*\blpg-product-warranty\b[^"]*"#', $html );
		$this->assertStringContainsString( 'wp-block-lpg-product-warranty', $html );
		$this->assertStringContainsString( 'alignwide', $html );
		$this->assertStringContainsString( '<span class="lpg-product-warranty__label">Cover:</span>', $html );
	}

	public function test_all_four_blocks_register_from_the_manifest_with_editor_scripts(): void {
		$this->assertContains( 'product-warranty', Registry::BLOCKS );
		$manifest = Registry::build_dir() . '/blocks-manifest.php';
		$this->assertFileIsReadable( $manifest, 'Run npm run build.' );
		$this->assertArrayHasKey( 'product-warranty', require $manifest );

		$registry = WP_Block_Type_Registry::get_instance();
		foreach ( self::ALL_BLOCKS as $name ) {
			if ( $registry->is_registered( $name ) ) {
				$registry->unregister( $name );
			}
		}

		Registry::register_blocks();

		foreach ( self::ALL_BLOCKS as $name ) {
			$this->assertTrue( $registry->is_registered( $name ), $name );
			$type = $registry->get_registered( $name );
			$this->assertNotEmpty( $type->editor_script_handles, $name . ' has an editor script.' );
			$this->assertIsCallable( $type->render_callback, $name );
		}
		$warranty = $registry->get_registered( 'lpg/product-warranty' );
		$this->assertSame( 'Warranty:', $warranty->attributes['label']['default'] );
		$this->assertSame( array( 'postId', 'postType' ), $warranty->uses_context );
		$this->assertSame( 'woocommerce', $warranty->category );
	}
}
