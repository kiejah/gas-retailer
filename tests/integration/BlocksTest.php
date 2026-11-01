<?php
/**
 * Plugin dynamic blocks (§11.9) and the Phase 3B block output rules.
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgFixtures.php';

use LPG\Commerce\Blocks\CompatibleProducts;
use LPG\Commerce\Blocks\ProductDocuments;
use LPG\Commerce\Blocks\Registry;
use LPG\Commerce\Blocks\SpecTable;
use LPG\Commerce\Relations\Compatibility;
use LPG\Commerce\Relations\Documents;
use LPG\Commerce\Specs\ProfileRepository;

class BlocksTest extends WP_UnitTestCase {

	use LpgFixtures;

	private const BLOCK_NAMES = array( 'lpg/spec-table', 'lpg/compatible-products', 'lpg/product-documents' );

	public function tear_down(): void {
		unset( $GLOBALS['product'] );
		parent::tear_down();
	}

	/**
	 * A product with a profile of three fields and values for two of them.
	 */
	private function product_with_specs(): WC_Product_Simple {
		$profile = $this->create_profile(
			array(
				$this->field( 'inlet_pressure', 'number', 'bar', false, array(), 'Inlet pressure' ),
				$this->field( 'colour', 'text', '', false, array(), 'Colour' ),
				$this->field( 'adjustable', 'boolean', '', false, array(), 'Adjustable' ),
			)
		);

		return $this->create_product(
			array(),
			array(
				'_lpg_spec_profile_id'     => $profile,
				'_lpg_spec_adjustable'     => 'no',
				'_lpg_spec_inlet_pressure' => '2.5',
			)
		);
	}

	private function pdf( string $file, int $bytes = 0 ): int {
		$id = self::factory()->attachment->create_object(
			array(
				'file'           => $file,
				'post_mime_type' => 'application/pdf',
				'post_title'     => 'Attachment title',
			)
		);
		if ( $bytes ) {
			wp_update_attachment_metadata( $id, array( 'filesize' => $bytes ) );
		}

		return $id;
	}

	private function assert_no_raw_injection( string $html ): void {
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '"onmouseover', $html );
		$this->assertStringNotContainsString( '" onmouseover', $html );
	}

	// ----- No product / no data ------------------------------------------------

	public function test_renderers_return_empty_without_a_product(): void {
		$post = self::factory()->post->create();

		foreach ( array( SpecTable::class, CompatibleProducts::class, ProductDocuments::class ) as $renderer ) {
			$this->assertSame( '', $renderer::render( array(), null ), $renderer );
			$this->assertSame( '', $renderer::render( array(), 0 ), $renderer );
			$this->assertSame( '', $renderer::render( array(), $post ), $renderer . ' (non-product post)' );
			$this->assertSame( '', $renderer::render( array(), 999999 ), $renderer . ' (missing ID)' );
		}
	}

	public function test_renderers_return_empty_without_data(): void {
		$product = $this->create_product();

		$this->assertSame( '', SpecTable::render( array( 'heading' => 'Specs' ), $product->get_id() ) );
		$this->assertSame( '', CompatibleProducts::render( array( 'heading' => 'Compatible' ), $product->get_id() ) );
		$this->assertSame( '', ProductDocuments::render( array( 'heading' => 'Documents' ), $product->get_id() ) );
	}

	public function test_compatible_products_empty_when_only_hidden_or_draft_products_are_linked(): void {
		$product = $this->create_product();
		$draft   = $this->create_product();
		$draft->set_status( 'draft' );
		$draft->save();
		update_post_meta( $product->get_id(), Compatibility::META, array( $draft->get_id() ) );

		$this->assertSame( '', CompatibleProducts::render( array(), $product->get_id() ) );
	}

	public function test_documents_empty_when_only_non_pdfs_are_stored(): void {
		$product = $this->create_product();
		$image   = self::factory()->attachment->create_object(
			array(
				'file'           => 'photo.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		update_post_meta(
			$product->get_id(),
			Documents::META,
			array(
				array(
					'attachment_id' => $image,
					'type'          => 'datasheet',
					'label'         => 'Photo',
				),
			)
		);

		$this->assertSame( '', ProductDocuments::render( array(), $product->get_id() ) );
	}

	// ----- Spec table ------------------------------------------------------------

	public function test_spec_table_rows_order_and_display(): void {
		$product = $this->product_with_specs();
		$html    = SpecTable::render( array( 'heading' => 'Technical specifications' ), $product->get_id() );

		$this->assertStringStartsWith( '<section class="lpg-spec-table">', $html );
		$this->assertStringContainsString( '<h2 class="lpg-spec-table__heading">Technical specifications</h2>', $html );
		$this->assertStringContainsString( '<caption class="screen-reader-text">Technical specifications</caption>', $html );

		preg_match_all( '#<th scope="row" class="lpg-spec-table__label">(.*?)</th><td class="lpg-spec-table__value">(.*?)</td>#', $html, $rows, PREG_SET_ORDER );
		$this->assertSame(
			array(
				array( 'Inlet pressure', '2.5 bar' ),
				array( 'Adjustable', 'No' ),
			),
			array_map( static fn( array $row ): array => array( $row[1], $row[2] ), $rows ),
			'Profile order, only fields with a value, formatted display.'
		);
	}

	public function test_spec_table_custom_and_empty_heading(): void {
		$product = $this->product_with_specs();

		$custom = SpecTable::render( array( 'heading' => 'Regulator data' ), $product->get_id() );
		$this->assertStringContainsString( '<h2 class="lpg-spec-table__heading">Regulator data</h2>', $custom );

		$none = SpecTable::render( array( 'heading' => '   ' ), $product->get_id() );
		$this->assertStringNotContainsString( '<h2', $none );
		$this->assertStringContainsString( '<caption class="screen-reader-text">Technical specifications</caption>', $none );
	}

	public function test_spec_table_falls_back_to_global_product(): void {
		$product            = $this->product_with_specs();
		$GLOBALS['product'] = $product;

		$this->assertStringContainsString( '<table', SpecTable::render( array(), null ) );
	}

	public function test_spec_table_escapes_raw_meta(): void {
		$profile = self::factory()->post->create(
			array(
				'post_type'   => 'lpg_spec_profile',
				'post_status' => 'publish',
			)
		);
		// Raw writes, as WooCommerce REST meta_data could do, bypassing our sanitising.
		update_post_meta(
			$profile,
			ProfileRepository::FIELDS_META,
			array(
				array(
					'key'     => 'colour',
					'label'   => '<script>alert(1)</script>Colour "onmouseover=alert(1)',
					'type'    => 'text',
					'unit'    => '',
					'options' => array(),
				),
				array(
					'key'     => 'valve',
					'label'   => 'Valve',
					'type'    => 'select',
					'unit'    => '',
					'options' => array( 'x" onmouseover="alert(1)', 'Clip-on' ),
				),
			)
		);
		$product = $this->create_product();
		update_post_meta( $product->get_id(), '_lpg_spec_profile_id', $profile );
		update_post_meta( $product->get_id(), '_lpg_spec_colour', '<script>alert(2)</script>Red "onmouseover=alert(2)' );
		update_post_meta( $product->get_id(), '_lpg_spec_valve', 'x" onmouseover="alert(1)' );

		$html = SpecTable::render( array( 'heading' => '<script>alert(3)</script>"onmouseover=x' ), $product->get_id() );

		$this->assertStringContainsString( '<table', $html );
		$this->assert_no_raw_injection( $html );
		$this->assertStringContainsString( '&quot;onmouseover=alert(2)', $html );
		$this->assertStringContainsString( 'x&quot; onmouseover=&quot;alert(1)', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(3)&lt;/script&gt;', $html );
	}

	// ----- Compatible products -----------------------------------------------------

	/**
	 * A product linked to $count compatible products.
	 *
	 * @return array{0: WC_Product_Simple, 1: WC_Product_Simple[]}
	 */
	private function product_with_compatibles( int $count ): array {
		$product = $this->create_product();
		$related = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$related[] = $this->create_product( array(), array(), (string) ( 100 + $i ) );
		}
		update_post_meta( $product->get_id(), Compatibility::META, array_map( static fn( $p ) => $p->get_id(), $related ) );

		return array( $product, $related );
	}

	public function test_compatible_products_cards(): void {
		list( $product, $related ) = $this->product_with_compatibles( 2 );
		$html                      = CompatibleProducts::render( array( 'heading' => 'Compatible products' ), $product->get_id() );

		$this->assertStringStartsWith( '<section class="lpg-compatible-products" style="--lpg-columns:4">', $html );
		$this->assertStringContainsString( '<h2 class="lpg-compatible-products__heading">Compatible products</h2>', $html );
		$this->assertSame( 2, substr_count( $html, '<li class="lpg-compatible-products__item">' ) );
		$this->assertStringContainsString( 'href="' . esc_url( $related[0]->get_permalink() ) . '"', $html );
		$this->assertStringContainsString( '<img', $html );
		$this->assertStringContainsString( 'woocommerce-Price-amount', $html );
		$this->assertStringNotContainsString( 'Enquire', $html );
	}

	public function test_compatible_products_limit_and_columns_are_clamped(): void {
		list( $product ) = $this->product_with_compatibles( 3 );
		$id              = $product->get_id();
		$items           = static fn( string $html ): int => substr_count( $html, '<li class="lpg-compatible-products__item">' );

		$low = CompatibleProducts::render(
			array(
				'columns' => 0,
				'limit'   => 0,
			),
			$id
		);
		$this->assertStringContainsString( 'style="--lpg-columns:2"', $low );
		$this->assertSame( 1, $items( $low ) );

		$high = CompatibleProducts::render(
			array(
				'columns' => 99,
				'limit'   => 99,
			),
			$id
		);
		$this->assertStringContainsString( 'style="--lpg-columns:6"', $high );
		$this->assertSame( 3, $items( $high ) );

		$two = CompatibleProducts::render(
			array(
				'columns' => '3',
				'limit'   => 2,
			),
			$id
		);
		$this->assertStringContainsString( 'style="--lpg-columns:3"', $two );
		$this->assertSame( 2, $items( $two ) );

		$junk = CompatibleProducts::render(
			array(
				'columns' => 'wide',
				'limit'   => array(),
			),
			$id
		);
		$this->assertStringContainsString( 'style="--lpg-columns:4"', $junk );
		$this->assertSame( 3, $items( $junk ) );
	}

	public function test_compatible_products_show_enquire_for_enquiry_only(): void {
		$product  = $this->create_product();
		$flagged  = $this->create_product( array(), array( '_lpg_enquiry_only' => 'yes' ), '500' );
		$no_price = $this->create_product( array(), array(), '' );
		update_post_meta( $product->get_id(), Compatibility::META, array( $flagged->get_id(), $no_price->get_id() ) );

		$html = CompatibleProducts::render( array(), $product->get_id() );

		$this->assertSame( 2, substr_count( $html, '<span class="lpg-compatible-products__price lpg-compatible-products__price--enquire">Enquire</span>' ) );
		$this->assertStringNotContainsString( 'woocommerce-Price-amount', $html, 'The stored price of an enquiry-only product is not shown.' );
	}

	public function test_compatible_products_escape_names_and_heading(): void {
		$product = $this->create_product();
		$evil    = $this->create_product();
		$evil->set_name( '<script>alert(1)</script>Hose "onmouseover=alert(1)' );
		$evil->save();
		update_post_meta( $product->get_id(), Compatibility::META, array( $evil->get_id() ) );

		$html = CompatibleProducts::render( array( 'heading' => '"onmouseover=x<script>y</script>' ), $product->get_id() );

		$this->assertStringContainsString( 'lpg-compatible-products__name', $html );
		$this->assert_no_raw_injection( $html );
	}

	// ----- Documents -----------------------------------------------------------------

	public function test_documents_list_only_pdfs_with_type_and_size(): void {
		$product = $this->create_product();
		$sheet   = $this->pdf( 'regulator-datasheet.pdf', 2048 );
		$manual  = $this->pdf( 'manual.pdf' );
		$image   = self::factory()->attachment->create_object(
			array(
				'file'           => 'photo.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		update_post_meta(
			$product->get_id(),
			Documents::META,
			array(
				array(
					'attachment_id' => $sheet,
					'type'          => 'datasheet',
					'label'         => 'Regulator datasheet',
				),
				array(
					'attachment_id' => $image,
					'type'          => 'manual',
					'label'         => 'Not a PDF',
				),
				array(
					'attachment_id' => $manual,
					'type'          => 'manual',
					'label'         => '',
				),
			)
		);

		$html = ProductDocuments::render( array( 'heading' => 'Product documents' ), $product->get_id() );

		$this->assertStringStartsWith( '<section class="lpg-product-documents">', $html );
		$this->assertStringContainsString( '<h2 class="lpg-product-documents__heading">Product documents</h2>', $html );
		$this->assertSame( 2, substr_count( $html, '<li class="lpg-product-documents__item' ) );
		$this->assertStringNotContainsString( 'Not a PDF', $html );
		$this->assertStringNotContainsString( 'photo.jpg', $html );
		$this->assertStringContainsString( 'href="' . esc_url( wp_get_attachment_url( $sheet ) ) . '" target="_blank" rel="noopener"', $html );
		$this->assertStringContainsString( '<span class="lpg-product-documents__label">Regulator datasheet</span> <span class="lpg-product-documents__meta">(Datasheet, PDF, 2 KB)</span>', $html );
		$this->assertStringContainsString( '<span class="lpg-product-documents__label">Attachment title</span> <span class="lpg-product-documents__meta">(Manual, PDF)</span>', $html, 'Empty label falls back to the attachment title; unknown size is omitted.' );
	}

	public function test_documents_escape_labels_and_urls(): void {
		$product = $this->create_product();
		$pdf     = $this->pdf( 'evil"onmouseover=alert(1)<script>.pdf' );
		update_post_meta(
			$product->get_id(),
			Documents::META,
			array(
				array(
					'attachment_id' => $pdf,
					'type'          => '"><script>alert(1)</script>',
					'label'         => '<script>alert(1)</script>Manual "onmouseover=alert(1)',
				),
			)
		);

		$html = ProductDocuments::render( array( 'heading' => '<script>x</script>' ), $product->get_id() );

		$this->assertStringContainsString( 'lpg-product-documents__link', $html );
		$this->assert_no_raw_injection( $html );
		$this->assertStringContainsString( '&quot;onmouseover=alert(1)', $html );
		$this->assertStringContainsString( 'lpg-product-documents__item--other', $html, 'Unknown type falls back to other.' );
	}

	// ----- Registry and block rendering ------------------------------------------------

	private function unregister_lpg_blocks(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		foreach ( self::BLOCK_NAMES as $name ) {
			if ( $registry->is_registered( $name ) ) {
				$registry->unregister( $name );
			}
		}
	}

	public function test_registry_registers_blocks_from_source_without_editor_script(): void {
		$this->unregister_lpg_blocks();

		Registry::register_from_source();

		$registry = WP_Block_Type_Registry::get_instance();
		foreach ( self::BLOCK_NAMES as $name ) {
			$this->assertTrue( $registry->is_registered( $name ), $name );
			$type = $registry->get_registered( $name );
			$this->assertSame( array(), $type->editor_script_handles, $name . ' has no editor script before the build.' );
			$this->assertIsCallable( $type->render_callback, $name );
			$this->assertSame( 'woocommerce', $type->category );
			$this->assertSame( array( 'postId', 'postType' ), $type->uses_context );
		}
		$this->assertSame( 4, $registry->get_registered( 'lpg/compatible-products' )->attributes['columns']['default'] );
		$this->assertSame( 8, $registry->get_registered( 'lpg/compatible-products' )->attributes['limit']['default'] );
		$this->assertFalse( has_filter( 'block_type_metadata', array( Registry::class, 'strip_source_assets' ) ) );
	}

	public function test_registry_register_blocks_registers_all_three(): void {
		$this->unregister_lpg_blocks();
		if ( ! is_readable( Registry::build_dir() . '/blocks-manifest.php' ) ) {
			$this->assertFileDoesNotExist( Registry::build_dir() . '/blocks-manifest.php' );
		}

		Registry::register_blocks();
		Registry::register_blocks(); // Idempotent: no "already registered" notice.

		foreach ( self::BLOCK_NAMES as $name ) {
			$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( $name ), $name );
		}
	}

	public function test_block_json_files_are_valid(): void {
		foreach ( Registry::BLOCKS as $dir ) {
			$file = Registry::source_dir() . '/' . $dir . '/block.json';
			$json = json_decode( (string) file_get_contents( $file ), true );
			$this->assertIsArray( $json, $file );
			$this->assertSame( 3, $json['apiVersion'] );
			$this->assertSame( 'lpg/' . $dir, $json['name'] );
			$this->assertSame( 'lpg-commerce-core', $json['textdomain'] );
			$this->assertSame( 'file:./index.js', $json['editorScript'] );
			$this->assertSame( 'file:./render.php', $json['render'] );
		}
	}

	public function test_render_block_with_post_id_context_produces_table(): void {
		$product = $this->product_with_specs();
		$context = static function ( array $context, array $parsed ) use ( $product ): array {
			if ( 'lpg/spec-table' === $parsed['blockName'] ) {
				$context['postId']   = $product->get_id();
				$context['postType'] = 'product';
			}
			return $context;
		};
		add_filter( 'render_block_context', $context, 10, 2 );

		$blocks = parse_blocks( '<!-- wp:lpg/spec-table /-->' );
		$html   = render_block( $blocks[0] );

		remove_filter( 'render_block_context', $context, 10 );

		$this->assertMatchesRegularExpression( '#^<section class="[^"]*\blpg-spec-table\b[^"]*"#', $html );
		$this->assertStringContainsString( 'wp-block-lpg-spec-table', $html );
		$this->assertStringContainsString( '<h2 class="lpg-spec-table__heading">Technical specifications</h2>', $html );
		$this->assertStringContainsString( '<td class="lpg-spec-table__value">2.5 bar</td>', $html );
	}

	public function test_render_block_compatible_products_applies_supports_and_columns(): void {
		list( $product ) = $this->product_with_compatibles( 1 );
		$context         = static function ( array $context ) use ( $product ): array {
			$context['postId'] = $product->get_id();
			return $context;
		};
		add_filter( 'render_block_context', $context );

		$html = render_block( parse_blocks( '<!-- wp:lpg/compatible-products {"columns":3,"align":"wide"} /-->' )[0] );

		remove_filter( 'render_block_context', $context );

		$this->assertStringContainsString( 'alignwide', $html );
		$this->assertStringContainsString( '--lpg-columns:3', $html );
		$this->assertStringContainsString( 'lpg-compatible-products__item', $html );
	}
}
