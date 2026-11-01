<?php
/**
 * Spec additionalProperty in product structured data (§11.8).
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgFixtures.php';

use LPG\Commerce\Seo\StructuredData;

class StructuredDataTest extends WP_UnitTestCase {

	use LpgFixtures;

	public function test_adds_property_values_and_keeps_existing_markup(): void {
		$profile = $this->create_profile(
			array(
				$this->field( 'outlet_pressure', 'number', 'mbar', false, array(), 'Outlet pressure' ),
				$this->field( 'material', 'text', '', false, array(), 'Material' ),
				$this->field( 'oven', 'boolean', '', false, array(), 'Oven' ),
			)
		);
		$product = $this->create_product(
			array( $this->create_category( 0, $profile ) ),
			array(
				'_lpg_spec_outlet_pressure' => '37',
				'_lpg_spec_material'        => 'Brass',
				'_lpg_spec_oven'            => 'no',
			)
		);

		$markup = apply_filters(
			'woocommerce_structured_data_product',
			array(
				'@type' => 'Product',
				'name'  => 'X',
			),
			$product
		);

		$this->assertSame( 'Product', $markup['@type'] );
		$this->assertSame( 'X', $markup['name'] );
		$this->assertSame(
			array(
				array(
					'@type'    => 'PropertyValue',
					'name'     => 'Outlet pressure',
					'value'    => 37,
					'unitText' => 'mbar',
				),
				array(
					'@type' => 'PropertyValue',
					'name'  => 'Material',
					'value' => 'Brass',
				),
				array(
					'@type' => 'PropertyValue',
					'name'  => 'Oven',
					'value' => false,
				),
			),
			$markup['additionalProperty']
		);
	}

	public function test_non_finite_number_falls_back_to_string(): void {
		$huge = str_repeat( '9', 400 );

		$this->assertSame( $huge, StructuredData::json_value( 'number', $huge ) );
		$this->assertSame( 37, StructuredData::json_value( 'number', '37' ) );
		$this->assertSame( 2.5, StructuredData::json_value( 'number', '2.5' ) );
		$this->assertTrue( StructuredData::json_value( 'boolean', 'yes' ) );
		$this->assertSame( 'Brass', StructuredData::json_value( 'text', 'Brass' ) );
	}

	public function test_no_specs_leaves_markup_untouched(): void {
		$product = $this->create_product();
		$markup  = apply_filters( 'woocommerce_structured_data_product', array( '@type' => 'Product' ), $product );

		$this->assertSame( array( '@type' => 'Product' ), $markup );
	}
}
