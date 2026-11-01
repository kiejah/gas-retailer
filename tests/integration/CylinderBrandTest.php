<?php
/**
 * Cylinder brand taxonomy tests.
 *
 * @package LPG\Commerce\Tests
 */

use LPG\Commerce\Cli\SeedCommand;
use LPG\Commerce\Taxonomies\CylinderBrand;
use LPG\Commerce\Taxonomies\CylinderBrands;

/**
 * Tests for LPG\Commerce\Taxonomies\CylinderBrand and CylinderBrands (§13.2).
 */
class CylinderBrandTest extends WP_UnitTestCase {

	public function tear_down(): void {
		$_POST = array();
		parent::tear_down();
	}

	private function brand( string $name, array $sizes = array() ): int {
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => CylinderBrand::TAXONOMY,
				'name'     => $name,
			)
		);
		if ( $sizes ) {
			CylinderBrand::save_sizes( $term_id, $sizes );
		}
		return $term_id;
	}

	private function submit( int $term_id, string $sizes, ?string $nonce = null ): void {
		$_POST = array(
			'lpg_cylinder_sizes_nonce' => $nonce ?? wp_create_nonce( 'lpg_save_cylinder_sizes' ),
			'lpg_sizes'                => $sizes,
		);
		( new CylinderBrand() )->handle_save( $term_id );
	}

	public function test_taxonomy_is_private_admin_only_and_uses_product_term_caps(): void {
		$tax = get_taxonomy( CylinderBrand::TAXONOMY );

		$this->assertSame( array(), $tax->object_type );
		$this->assertFalse( $tax->public );
		$this->assertTrue( $tax->show_ui );
		$this->assertFalse( $tax->show_in_rest );
		$this->assertSame( 'manage_product_terms', $tax->cap->manage_terms );
		$this->assertSame( 'edit_product_terms', $tax->cap->edit_terms );
	}

	public function test_sanitize_sizes_trims_dedupes_and_limits(): void {
		$this->assertSame( array( '6kg', '13kg' ), CylinderBrand::sanitize_sizes( "  6kg \r\n\n13kg\n6KG\n<b></b>" ) );
		$this->assertSame( array( str_repeat( 'x', 20 ) ), CylinderBrand::sanitize_sizes( array( str_repeat( 'x', 30 ) ) ) );
		$this->assertCount( 20, CylinderBrand::sanitize_sizes( array_map( static fn( $i ) => $i . 'kg', range( 1, 30 ) ) ) );
		$this->assertSame( array(), CylinderBrand::sanitize_sizes( 42 ) );
		$this->assertSame( array( '6 kg' ), CylinderBrand::sanitize_sizes( array( '<script>x</script>6 kg', array( 'nested' ) ) ) );
	}

	public function test_shop_manager_saves_sizes_from_term_form(): void {
		$term_id = $this->brand( 'Test brand' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$this->submit( $term_id, "3kg\n 6kg \n3kg" );

		$this->assertSame( array( '3kg', '6kg' ), CylinderBrand::get_sizes( $term_id ) );
	}

	public function test_save_requires_valid_nonce(): void {
		$term_id = $this->brand( 'Test brand', array( '6kg' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->submit( $term_id, '50kg', 'invalid' );

		$this->assertSame( array( '6kg' ), CylinderBrand::get_sizes( $term_id ) );
	}

	public function test_subscriber_cannot_save_sizes(): void {
		$term_id = $this->brand( 'Test brand', array( '6kg' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->submit( $term_id, '50kg' );

		$this->assertSame( array( '6kg' ), CylinderBrand::get_sizes( $term_id ) );
	}

	public function test_term_form_fields_and_sizes_column(): void {
		$term_id = $this->brand( 'Test brand', array( '6kg', '13kg' ) );
		$service = new CylinderBrand();

		ob_start();
		$service->render_edit_field( get_term( $term_id ) );
		$html = ob_get_clean();
		$this->assertStringContainsString( "6kg\n13kg</textarea>", $html );
		$this->assertStringContainsString( 'name="lpg_cylinder_sizes_nonce"', $html );

		ob_start();
		$service->render_add_field();
		$this->assertStringContainsString( 'name="lpg_sizes"', ob_get_clean() );

		$columns = $service->columns(
			array(
				'name'  => 'Name',
				'posts' => 'Count',
			)
		);
		$this->assertArrayNotHasKey( 'posts', $columns );
		$this->assertArrayHasKey( 'lpg_sizes', $columns );
		$this->assertSame( '6kg, 13kg', $service->column_content( '', 'lpg_sizes', $term_id ) );
		$this->assertSame( '&mdash;', $service->column_content( '', 'lpg_sizes', $this->brand( 'Empty brand' ) ) );
	}

	public function test_all_returns_brands_sorted_by_name_with_sizes(): void {
		$zeta  = $this->brand( 'Zeta Gas', array( '13kg' ) );
		$alpha = $this->brand( 'Alpha & Co', array( '6kg', '13kg' ) );
		$empty = $this->brand( 'Mid Gas' );

		$all = CylinderBrands::all();

		$this->assertSame( array( 'Alpha & Co', 'Mid Gas', 'Zeta Gas' ), wp_list_pluck( $all, 'name' ) );
		$this->assertSame(
			array(
				'id'    => $alpha,
				'name'  => 'Alpha & Co',
				'slug'  => 'alpha-co',
				'sizes' => array( '6kg', '13kg' ),
			),
			$all[0]
		);
		$this->assertSame( $empty, $all[1]['id'] );
		$this->assertSame( array(), $all[1]['sizes'] );
		$this->assertSame( array( '13kg' ), $all[2]['sizes'] );
		$this->assertSame( $zeta, $all[2]['id'] );
	}

	public function test_seed_creates_starter_brands_once_and_never_overwrites(): void {
		$first = SeedCommand::run();

		$this->assertSame( 3, $first['brands_created'] );
		$this->assertSame( 0, $first['brands_existing'] );
		$this->assertSame( array(), $first['errors'] );
		$expected = array(
			array( 'K-Gas', 'k-gas', array( '6kg', '13kg' ) ),
			array( 'Pro Gas', 'pro-gas', array( '6kg', '13kg' ) ),
			array( 'Total', 'total', array( '6kg', '13kg' ) ),
		);
		$actual   = array_map( static fn( $b ) => array( $b['name'], $b['slug'], $b['sizes'] ), CylinderBrands::all() );
		$this->assertSame( $expected, $actual );

		// The client edits a seeded brand, then the seed runs again.
		$total = get_term_by( 'slug', 'total', CylinderBrand::TAXONOMY );
		CylinderBrand::save_sizes( $total->term_id, array( '13kg', '50kg' ) );

		$second = SeedCommand::run();

		$this->assertSame( 0, $second['brands_created'] );
		$this->assertSame( 3, $second['brands_existing'] );
		$this->assertCount( 3, CylinderBrands::all() );
		$this->assertSame( array( '13kg', '50kg' ), CylinderBrand::get_sizes( $total->term_id ) );
	}

	public function test_seed_recreates_only_a_deleted_brand(): void {
		SeedCommand::run();
		$pro = get_term_by( 'slug', 'pro-gas', CylinderBrand::TAXONOMY );
		wp_delete_term( $pro->term_id, CylinderBrand::TAXONOMY );

		$summary = SeedCommand::run();

		$this->assertSame( 1, $summary['brands_created'] );
		$this->assertSame( 2, $summary['brands_existing'] );
		$this->assertCount( 3, CylinderBrands::all() );
	}
}
