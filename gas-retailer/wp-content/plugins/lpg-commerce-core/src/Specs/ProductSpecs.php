<?php
/**
 * Public read API for a product's technical specifications.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Specs;

defined( 'ABSPATH' ) || exit;

/**
 * ProductSpecs::get() — §11.6.
 */
final class ProductSpecs {

	/**
	 * Specs of a product: only fields with a value, in profile order.
	 *
	 * Values no longer valid for the current definition (e.g. a removed select
	 * option) are skipped. Orphaned values of other profiles are not shown (C5).
	 *
	 * @param int $product_id Product (or variation) ID.
	 * @return array<int, array{key: string, label: string, type: string, unit: string, value: string, display: string}>
	 */
	public static function get( int $product_id ): array {
		$product_id = ProfileResolver::parent_id( $product_id );
		$profile_id = $product_id ? ProfileResolver::for_product( $product_id ) : null;
		if ( ! $profile_id ) {
			return array();
		}

		$meta  = get_post_meta( $product_id );
		$specs = array();
		foreach ( ProfileRepository::get_fields( $profile_id ) as $field ) {
			$meta_key = ProfileRepository::value_meta_key( $field['key'] );
			if ( ! isset( $meta[ $meta_key ][0] ) ) {
				continue;
			}
			$value = ProfileRepository::sanitize_value( $field, maybe_unserialize( $meta[ $meta_key ][0] ) )['value'];
			if ( '' === $value ) {
				continue;
			}
			$specs[] = array(
				'key'     => $field['key'],
				'label'   => $field['label'],
				'type'    => $field['type'],
				'unit'    => $field['unit'],
				'value'   => $value,
				'display' => ProfileRepository::format_display( $field, $value ),
			);
		}

		return $specs;
	}
}
