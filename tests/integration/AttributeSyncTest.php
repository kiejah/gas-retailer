<?php
/**
 * Filterable spec values mirrored to pa_ attributes (§11.3, C3, C5).
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgFixtures.php';

use LPG\Commerce\Specs\AttributeSync;

class AttributeSyncTest extends WP_UnitTestCase {

	use LpgFixtures;

	private int $profile;
	private int $category;

	public function set_up(): void {
		parent::set_up();
		$this->profile  = $this->create_profile(
			array(
				$this->field( 'outlet_pressure', 'number', 'mbar', true, array(), 'Outlet pressure' ),
				$this->field( 'has_gauge', 'boolean', '', true ),
				$this->field( 'material' ),
			)
		);
		$this->category = $this->create_category( 0, $this->profile );
	}

	private function term_names( int $product_id, string $taxonomy ): array {
		return wp_get_object_terms( $product_id, $taxonomy, array( 'fields' => 'names' ) );
	}

	private function reload( int $id ): WC_Product {
		return wc_get_product( $id );
	}

	public function test_add_update_and_clear(): void {
		$product = $this->create_product( array( $this->category ), array( '_lpg_spec_outlet_pressure' => '37' ) );
		$id      = $product->get_id();

		$attributes = $this->reload( $id )->get_attributes();
		$this->assertArrayHasKey( 'pa_outlet_pressure', $attributes );
		$this->assertFalse( $attributes['pa_outlet_pressure']->get_visible() );
		$this->assertFalse( $attributes['pa_outlet_pressure']->get_variation() );
		$this->assertSame( array( '37 mbar' ), $this->term_names( $id, 'pa_outlet_pressure' ) );
		$this->assertSame( array( 'pa_outlet_pressure' ), get_post_meta( $id, AttributeSync::TRACK_META, true ) );

		$product = $this->reload( $id );
		$product->update_meta_data( '_lpg_spec_outlet_pressure', '30' );
		$product->save();
		$this->assertSame( array( '30 mbar' ), $this->term_names( $id, 'pa_outlet_pressure' ) );

		$product = $this->reload( $id );
		$product->delete_meta_data( '_lpg_spec_outlet_pressure' );
		$product->save();
		$this->assertArrayNotHasKey( 'pa_outlet_pressure', $this->reload( $id )->get_attributes() );
		$this->assertSame( array(), $this->term_names( $id, 'pa_outlet_pressure' ) );
		$this->assertFalse( metadata_exists( 'post', $id, AttributeSync::TRACK_META ) );
	}

	public function test_boolean_no_is_synced_as_no_term(): void {
		$product = $this->create_product( array( $this->category ), array( '_lpg_spec_has_gauge' => 'no' ) );

		$this->assertSame( array( 'No' ), $this->term_names( $product->get_id(), 'pa_has_gauge' ) );
	}

	public function test_non_filterable_field_is_not_synced(): void {
		$product = $this->create_product( array( $this->category ), array( '_lpg_spec_material' => 'Brass' ) );

		$this->assertArrayNotHasKey( 'pa_material', $this->reload( $product->get_id() )->get_attributes() );
	}

	public function test_other_attributes_are_preserved(): void {
		$local = new WC_Product_Attribute();
		$local->set_name( 'Colour' );
		$local->set_options( array( 'Red' ) );
		$local->set_visible( true );

		$product = new WC_Product_Simple();
		$product->set_name( '[DEMO] With attributes' );
		$product->set_regular_price( '10' );
		$product->set_category_ids( array( $this->category ) );
		$product->set_attributes( array( $local ) );
		$product->update_meta_data( '_lpg_spec_outlet_pressure', '37' );
		$product->save();
		$id = $product->get_id();

		$product = $this->reload( $id );
		$product->delete_meta_data( '_lpg_spec_outlet_pressure' );
		$product->save();

		$attributes = $this->reload( $id )->get_attributes();
		$this->assertArrayHasKey( 'colour', $attributes );
		$this->assertSame( array( 'Red' ), $attributes['colour']->get_options() );
		$this->assertArrayNotHasKey( 'pa_outlet_pressure', $attributes );
	}

	public function test_untracked_attribute_with_same_taxonomy_is_never_touched(): void {
		AttributeSync::ensure_attribute( 'outlet_pressure', 'Outlet pressure' );
		$term = wp_insert_term( 'Manual value', 'pa_outlet_pressure' );

		$manual = new WC_Product_Attribute();
		$manual->set_id( wc_attribute_taxonomy_id_by_name( 'outlet_pressure' ) );
		$manual->set_name( 'pa_outlet_pressure' );
		$manual->set_options( array( $term['term_id'] ) );
		$manual->set_visible( true );

		$product = new WC_Product_Simple();
		$product->set_name( '[DEMO] Manual attribute' );
		$product->set_regular_price( '10' );
		$product->set_category_ids( array( $this->category ) );
		$product->set_attributes( array( $manual ) );
		$product->update_meta_data( '_lpg_spec_outlet_pressure', '37' );
		$product->save();
		$id = $product->get_id();

		$this->assertSame( array( 'Manual value' ), $this->term_names( $id, 'pa_outlet_pressure' ) );
		$this->assertTrue( $this->reload( $id )->get_attributes()['pa_outlet_pressure']->get_visible() );

		$product = $this->reload( $id );
		$product->delete_meta_data( '_lpg_spec_outlet_pressure' );
		$product->save();
		$this->assertSame( array( 'Manual value' ), $this->term_names( $id, 'pa_outlet_pressure' ) );
	}

	public function test_tracked_attribute_removed_when_profile_changes_but_value_kept(): void {
		$product = $this->create_product( array( $this->category ), array( '_lpg_spec_outlet_pressure' => '37' ) );
		$id      = $product->get_id();
		$this->assertArrayHasKey( 'pa_outlet_pressure', $this->reload( $id )->get_attributes() );

		$other   = $this->create_profile( array( $this->field( 'capacity', 'number', 'kg', true ) ) );
		$product = $this->reload( $id );
		$product->update_meta_data( '_lpg_spec_profile_id', $other );
		$product->save();

		$this->assertArrayNotHasKey( 'pa_outlet_pressure', $this->reload( $id )->get_attributes() );
		$this->assertSame( '37', get_post_meta( $id, '_lpg_spec_outlet_pressure', true ), 'Orphaned value is kept (C5).' );
	}
}
