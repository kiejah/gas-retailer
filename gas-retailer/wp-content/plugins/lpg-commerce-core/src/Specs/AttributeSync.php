<?php
/**
 * Mirrors filterable spec values to WooCommerce global attributes.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Specs;

use LPG\Commerce\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Filterable sync (§11.3, C3, C5, C10).
 *
 * Runs on every product object save (admin, REST, CLI, import), before the
 * data store writes, so it only adjusts the object and never saves itself
 * (no recursion). Attributes LPG added are tracked in `_lpg_synced_attributes`;
 * untracked attributes are never touched.
 */
final class AttributeSync implements Service {

	public const TRACK_META = '_lpg_synced_attributes';

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_before_product_object_save', array( self::class, 'sync' ) );
	}

	/**
	 * Bring the product's LPG-managed `pa_` attributes in line with its spec values.
	 *
	 * @param \WC_Product $product Product being saved (not yet written).
	 */
	public static function sync( $product ): void {
		if ( ! $product instanceof \WC_Product || $product->is_type( 'variation' ) ) {
			return;
		}

		$desired    = array();
		$profile_id = ProfileResolver::resolve(
			(int) $product->get_meta( ProfileResolver::OVERRIDE_META, true, 'edit' ),
			(array) $product->get_category_ids( 'edit' )
		);
		if ( $profile_id ) {
			foreach ( ProfileRepository::get_fields( $profile_id ) as $field ) {
				if ( ! $field['filterable'] ) {
					continue;
				}
				$value = ProfileRepository::sanitize_value( $field, $product->get_meta( ProfileRepository::value_meta_key( $field['key'] ), true, 'edit' ) )['value'];
				if ( '' === $value ) {
					continue;
				}
				$desired[ wc_attribute_taxonomy_name( $field['key'] ) ] = array(
					'field'   => $field,
					'display' => ProfileRepository::format_display( $field, $value ),
				);
			}
		}

		$tracked     = self::tracked( $product );
		$attributes  = $product->get_attributes( 'edit' );
		$new_tracked = array();
		$changed     = false;

		foreach ( $desired as $taxonomy => $item ) {
			$existing   = $attributes[ $taxonomy ] ?? null;
			$is_tracked = in_array( $taxonomy, $tracked, true );
			if ( $existing instanceof \WC_Product_Attribute && ( ! $is_tracked || $existing->get_variation() ) ) {
				continue;
				// Added by someone else, or used for variations: never touched.
			}
			$attribute_id = self::ensure_attribute( $item['field']['key'], $item['field']['label'] );
			$term_id      = $attribute_id ? self::term_id( $taxonomy, $item['display'] ) : 0;
			if ( ! $term_id ) {
				continue;
			}
			$new_tracked[] = $taxonomy;

			if ( $existing instanceof \WC_Product_Attribute && array( $term_id ) === array_map( 'intval', $existing->get_options() ) && ! $existing->get_visible() ) {
				continue;
				// Already in sync: avoid needless term writes.
			}
			$attribute = new \WC_Product_Attribute();
			$attribute->set_id( $attribute_id );
			$attribute->set_name( $taxonomy );
			$attribute->set_options( array( $term_id ) );
			$attribute->set_position( $existing instanceof \WC_Product_Attribute ? $existing->get_position() : count( $attributes ) );
			$attribute->set_visible( false );
			$attribute->set_variation( false );
			$attributes[ $taxonomy ] = $attribute;
			$changed                 = true;
		}//end foreach

		foreach ( array_diff( $tracked, $new_tracked ) as $taxonomy ) {
			if ( isset( $attributes[ $taxonomy ] ) && $attributes[ $taxonomy ] instanceof \WC_Product_Attribute && ! $attributes[ $taxonomy ]->get_variation() ) {
				unset( $attributes[ $taxonomy ] );
				$changed = true;
			}
		}

		if ( $changed ) {
			$product->set_attributes( array_filter( $attributes ) );
		}
		if ( $new_tracked !== $tracked ) {
			if ( $new_tracked ) {
				$product->update_meta_data( self::TRACK_META, $new_tracked );
			} else {
				$product->delete_meta_data( self::TRACK_META );
			}
		}
	}

	/**
	 * Ensure the global attribute `pa_{key}` exists (and its taxonomy is registered
	 * in this request). An existing attribute is never renamed (C10).
	 *
	 * @param string $key   Field key (attribute slug).
	 * @param string $label Attribute label used when creating it.
	 * @return int Attribute ID, or 0 on failure.
	 */
	public static function ensure_attribute( string $key, string $label ): int {
		if ( ! preg_match( ProfileRepository::KEY_PATTERN, $key ) ) {
			return 0;
		}
		$attribute_id = wc_attribute_taxonomy_id_by_name( $key );
		if ( ! $attribute_id ) {
			// A taxonomy registered without an attribute row (deleted earlier in
			// this request) is stale; wc_create_attribute() refuses its slug.
			if ( taxonomy_exists( wc_attribute_taxonomy_name( $key ) ) ) {
				unregister_taxonomy( wc_attribute_taxonomy_name( $key ) );
			}
			$attribute_id = wc_create_attribute(
				array(
					'name'         => $label,
					'slug'         => $key,
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => false,
				)
			);
			if ( is_wp_error( $attribute_id ) ) {
				return 0;
			}
		}

		$taxonomy = wc_attribute_taxonomy_name( $key );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy(
				$taxonomy,
				array( 'product' ),
				array(
					'labels'       => array( 'name' => $label ),
					'hierarchical' => false,
					'show_ui'      => false,
					'query_var'    => true,
					'rewrite'      => false,
				)
			);
		}

		return (int) $attribute_id;
	}

	/**
	 * Taxonomy names currently tracked on the product.
	 *
	 * @param \WC_Product $product Product.
	 * @return string[]
	 */
	private static function tracked( \WC_Product $product ): array {
		$tracked = $product->get_meta( self::TRACK_META, true, 'edit' );

		return is_array( $tracked ) ? array_values( array_filter( $tracked, 'is_string' ) ) : array();
	}

	/**
	 * Term ID for a display value, creating the term if needed.
	 *
	 * @param string $taxonomy `pa_` taxonomy.
	 * @param string $name     Term name.
	 */
	private static function term_id( string $taxonomy, string $name ): int {
		$existing = term_exists( $name, $taxonomy );
		if ( is_array( $existing ) ) {
			return (int) $existing['term_id'];
		}
		$inserted = wp_insert_term( $name, $taxonomy );
		if ( is_wp_error( $inserted ) ) {
			return 'term_exists' === $inserted->get_error_code() ? (int) $inserted->get_error_data() : 0;
		}

		return (int) $inserted['term_id'];
	}
}
