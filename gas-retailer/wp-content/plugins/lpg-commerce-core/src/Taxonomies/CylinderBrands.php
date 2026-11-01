<?php
/**
 * Read API for cylinder brands.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * Static read access to `lpg_cylinder_brand` (§13.2). Not a service: the
 * exchange form, its REST validation and its WhatsApp message all read the
 * brand list from here, so brands added in the dashboard appear everywhere.
 */
final class CylinderBrands {

	/**
	 * All brands sorted by name, with their sizes. `name` is decoded plain
	 * text: escape it on output.
	 *
	 * @return array<int, array{id: int, name: string, slug: string, sizes: string[]}>
	 */
	public static function all(): array {
		if ( ! taxonomy_exists( CylinderBrand::TAXONOMY ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => CylinderBrand::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( ! is_array( $terms ) ) {
			return array();
		}

		$brands = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$brands[] = array(
				'id'    => (int) $term->term_id,
				'name'  => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
				'slug'  => $term->slug,
				'sizes' => CylinderBrand::get_sizes( (int) $term->term_id ),
			);
		}
		return $brands;
	}
}
