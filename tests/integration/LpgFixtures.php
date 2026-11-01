<?php
/**
 * Shared fixtures for LPG Commerce Core integration tests (not a test case).
 *
 * @package LPG\Commerce\Tests
 */

use LPG\Commerce\Specs\ProfilePostType;

/**
 * Helpers to create profiles, categories and products.
 */
trait LpgFixtures {

	/**
	 * Create a spec profile with field definitions.
	 *
	 * @param array  $fields Field rows.
	 * @param string $status Post status.
	 */
	protected function create_profile( array $fields, string $status = 'publish', string $title = 'Test profile' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => ProfilePostType::POST_TYPE,
				'post_title'  => $title,
				'post_status' => $status,
			)
		);
		ProfilePostType::save_fields( $id, $fields );

		return $id;
	}

	/**
	 * Field definition row.
	 */
	protected function field( string $key, string $type = 'text', string $unit = '', bool $filterable = false, array $options = array(), string $label = '' ): array {
		return array(
			'key'        => $key,
			'label'      => '' !== $label ? $label : ucfirst( str_replace( '_', ' ', $key ) ),
			'type'       => $type,
			'unit'       => $unit,
			'options'    => $options,
			'filterable' => $filterable,
			'help'       => '',
		);
	}

	/**
	 * Create a product_cat term, optionally with a default profile.
	 */
	protected function create_category( int $parent_id = 0, int $profile_id = 0 ): int {
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'parent'   => $parent_id,
			)
		);
		if ( $profile_id ) {
			update_term_meta( $term_id, 'lpg_spec_profile_id', $profile_id );
		}

		return $term_id;
	}

	/**
	 * Create and save a simple product.
	 *
	 * @param array $category_ids product_cat IDs.
	 * @param array $meta         Meta to set before saving.
	 */
	protected function create_product( array $category_ids = array(), array $meta = array(), string $price = '100' ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( '[DEMO] Test product' );
		$product->set_regular_price( $price );
		$product->set_status( 'publish' );
		$product->set_category_ids( $category_ids );
		foreach ( $meta as $key => $value ) {
			$product->update_meta_data( $key, $value );
		}
		$product->save();

		return $product;
	}
}
