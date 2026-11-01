<?php
/**
 * Field definitions, value sanitisation, ProductSpecs::get and the panel save.
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgFixtures.php';

use LPG\Commerce\Specs\ProductSpecs;
use LPG\Commerce\Specs\ProductSpecsPanel;
use LPG\Commerce\Specs\ProfileRepository;

class ProductSpecsTest extends WP_UnitTestCase {

	use LpgFixtures;

	public function test_definition_validation_drops_invalid_rows_and_keeps_valid_ones(): void {
		$result = ProfileRepository::validate_definitions(
			array(
				$this->field( 'capacity', 'number', 'kg' ),
				$this->field( 'Bad-Key' ),
				$this->field( '9starts_with_digit' ),
				$this->field( str_repeat( 'a', 29 ) ),
				$this->field( 'capacity' ),
				$this->field( 'colour', 'colour' ),
				$this->field( 'valve', 'select' ),
				array(
					'key'   => '',
					'label' => '',
				),
				$this->field( 'valve_type', 'select', '', true, array( 'Clip-on', '', 'Clip-on', 'Screw-on' ) ),
			)
		);

		$this->assertSame( array( 'capacity', 'valve_type' ), wp_list_pluck( $result['fields'], 'key' ) );
		$this->assertSame( array( 'Clip-on', 'Screw-on' ), $result['fields'][1]['options'] );
		$this->assertCount( 6, $result['errors'] );
	}

	public function test_lpg_reserved_key_profile_id_is_rejected(): void {
		$this->assertContains( 'profile_id', ProfileRepository::RESERVED_KEYS );

		$result = ProfileRepository::validate_definitions(
			array(
				$this->field( 'profile_id' ),
				$this->field( 'capacity', 'number', 'kg' ),
			)
		);

		$this->assertSame( array( 'capacity' ), wp_list_pluck( $result['fields'], 'key' ) );
		$this->assertCount( 1, $result['errors'] );
		$this->assertStringContainsString( 'profile_id', $result['errors'][0] );
	}

	public function test_filterable_reserved_key_is_rejected(): void {
		$result = ProfileRepository::validate_definitions( array( $this->field( 'type', 'text', '', true ) ) );

		$this->assertSame( array(), $result['fields'] );
		$this->assertCount( 1, $result['errors'] );
	}

	public function test_save_fields_creates_missing_global_attribute(): void {
		$this->create_profile( array( $this->field( 'outlet_pressure', 'number', 'mbar', true, array(), 'Outlet pressure' ) ) );

		$this->assertGreaterThan( 0, wc_attribute_taxonomy_id_by_name( 'outlet_pressure' ) );
		$this->assertTrue( taxonomy_exists( 'pa_outlet_pressure' ) );
	}

	/**
	 * Each type stores its documented form; invalid input is rejected.
	 *
	 * @dataProvider value_provider
	 */
	public function test_value_sanitisation( array $field, $raw, string $expected, bool $error ): void {
		$result = ProfileRepository::sanitize_value( $field, $raw );

		$this->assertSame( $expected, $result['value'] );
		$this->assertSame( $error, $result['error'] );
	}

	public function value_provider(): array {
		$number  = array( 'type' => 'number' );
		$select  = array(
			'type'    => 'select',
			'options' => array( 'Clip-on', 'Screw-on' ),
		);
		$boolean = array( 'type' => 'boolean' );
		$text    = array( 'type' => 'text' );

		return array(
			'text sanitised'          => array( $text, ' <b>Brass</b> ', 'Brass', false ),
			'number plain'            => array( $number, '37', '37', false ),
			'number decimal'          => array( $number, '2.5', '2.5', false ),
			'number comma decimal'    => array( $number, '2,5', '2.5', false ),
			'number negative'         => array( $number, '-10', '-10', false ),
			'number exponent'         => array( $number, '1e3', '', true ),
			'number thousands comma'  => array( $number, '1,000', '', true ),
			'number thousands mixed'  => array( $number, '1,000.5', '', true ),
			'number text'             => array( $number, '37 mbar', '', true ),
			'number empty'            => array( $number, '', '', false ),
			'number 20 digits'        => array( $number, '12345678901234567890', '12345678901234567890', false ),
			'number 20 digits dec'    => array( $number, '-0.0012345678901234567890', '-0.0012345678901234567890', false ),
			'number 21 digits'        => array( $number, '123456789012345678901', '', true ),
			'number 21 digits dec'    => array( $number, '1234567890.12345678901', '', true ),
			'number huge'             => array( $number, str_repeat( '9', 400 ), '', true ),
			'select valid'            => array( $select, 'Clip-on', 'Clip-on', false ),
			'select invalid'          => array( $select, 'Bayonet', '', true ),
			'boolean yes'             => array( $boolean, 'yes', 'yes', false ),
			'boolean no is a value'   => array( $boolean, 'no', 'no', false ),
			'boolean empty (not set)' => array( $boolean, '', '', false ),
			'boolean junk'            => array( $boolean, 'maybe', '', true ),
		);
	}

	public function test_get_returns_shape_in_profile_order_with_display_formatting(): void {
		$profile = $this->create_profile(
			array(
				$this->field( 'regulator_type', 'select', '', false, array( 'Low pressure', 'High pressure' ) ),
				$this->field( 'outlet_pressure', 'number', 'mbar' ),
				$this->field( 'material' ),
				$this->field( 'oven', 'boolean' ),
				$this->field( 'has_gauge', 'boolean' ),
			)
		);
		$product = $this->create_product(
			array( $this->create_category( 0, $profile ) ),
			array(
				'_lpg_spec_has_gauge'       => 'no',
				'_lpg_spec_outlet_pressure' => '37',
				'_lpg_spec_oven'            => 'yes',
				'_lpg_spec_regulator_type'  => 'Low pressure',
				'_lpg_spec_orphan'          => 'kept but hidden',
			)
		);

		$specs = ProductSpecs::get( $product->get_id() );

		$this->assertSame( array( 'regulator_type', 'outlet_pressure', 'oven', 'has_gauge' ), wp_list_pluck( $specs, 'key' ) );
		$this->assertSame(
			array(
				'key'     => 'outlet_pressure',
				'label'   => 'Outlet pressure',
				'type'    => 'number',
				'unit'    => 'mbar',
				'value'   => '37',
				'display' => '37 mbar',
			),
			$specs[1]
		);
		$this->assertSame( 'Low pressure', $specs[0]['display'] );
		$this->assertSame( 'Yes', $specs[2]['display'] );
		$this->assertSame( 'No', $specs[3]['display'] );
		$this->assertSame( 'kept but hidden', get_post_meta( $product->get_id(), '_lpg_spec_orphan', true ) );
	}

	public function test_get_skips_value_no_longer_valid_and_returns_empty_without_profile(): void {
		$profile = $this->create_profile( array( $this->field( 'valve_type', 'select', '', false, array( 'Clip-on' ) ) ) );
		$product = $this->create_product( array( $this->create_category( 0, $profile ) ), array( '_lpg_spec_valve_type' => 'Removed option' ) );

		$this->assertSame( array(), ProductSpecs::get( $product->get_id() ) );
		$this->assertSame( array(), ProductSpecs::get( $this->create_product()->get_id() ) );
	}

	public function test_panel_apply_saves_deletes_and_rejects(): void {
		$profile = $this->create_profile(
			array(
				$this->field( 'outlet_pressure', 'number', 'mbar' ),
				$this->field( 'valve_type', 'select', '', false, array( 'Clip-on', 'Screw-on' ) ),
				$this->field( 'material' ),
				$this->field( 'oven', 'boolean' ),
			)
		);
		$product = $this->create_product(
			array( $this->create_category( 0, $profile ) ),
			array(
				'_lpg_spec_valve_type' => 'Clip-on',
				'_lpg_spec_material'   => 'Brass',
			)
		);

		$errors = ProductSpecsPanel::apply(
			$product,
			0,
			$profile,
			array(
				'outlet_pressure' => '2,5',
				'valve_type'      => 'Bayonet',
				'material'        => '',
				'oven'            => 'no',
				'not_in_profile'  => 'ignored',
			)
		);
		$product->save();
		$id = $product->get_id();

		$this->assertCount( 1, $errors );
		$this->assertSame( '2.5', get_post_meta( $id, '_lpg_spec_outlet_pressure', true ) );
		$this->assertSame( 'Clip-on', get_post_meta( $id, '_lpg_spec_valve_type', true ), 'Rejected select keeps the previous value.' );
		$this->assertFalse( metadata_exists( 'post', $id, '_lpg_spec_material' ), 'Empty value deletes the meta.' );
		$this->assertSame( 'no', get_post_meta( $id, '_lpg_spec_oven', true ) );
		$this->assertFalse( metadata_exists( 'post', $id, '_lpg_spec_not_in_profile' ) );
	}

	public function test_panel_save_through_woocommerce_hook_with_nonce(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$profile  = $this->create_profile( array( $this->field( 'capacity', 'number', 'kg' ) ) );
		$override = $this->create_profile( array( $this->field( 'material' ) ) );
		$product  = $this->create_product( array( $this->create_category( 0, $profile ) ) );

		$_POST = array(
			'lpg_product_specs_nonce'   => wp_create_nonce( 'lpg_save_product_specs' ),
			'_lpg_spec_profile_id'      => (string) $override,
			'lpg_spec_rendered_profile' => (string) $profile,
			'lpg_spec'                  => array( 'capacity' => '13' ),
		);
		do_action( 'woocommerce_admin_process_product_object', $product );
		$product->save();
		$_POST = array();

		$this->assertSame( '13', get_post_meta( $product->get_id(), '_lpg_spec_capacity', true ) );
		$this->assertSame( $override, (int) get_post_meta( $product->get_id(), '_lpg_spec_profile_id', true ) );
	}

	public function test_panel_save_ignored_without_valid_nonce(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$profile = $this->create_profile( array( $this->field( 'capacity', 'number', 'kg' ) ) );
		$product = $this->create_product( array( $this->create_category( 0, $profile ) ) );

		$_POST = array(
			'lpg_product_specs_nonce'   => 'bad',
			'lpg_spec_rendered_profile' => (string) $profile,
			'lpg_spec'                  => array( 'capacity' => '13' ),
		);
		do_action( 'woocommerce_admin_process_product_object', $product );
		$product->save();
		$_POST = array();

		$this->assertFalse( metadata_exists( 'post', $product->get_id(), '_lpg_spec_capacity' ) );
	}
}
