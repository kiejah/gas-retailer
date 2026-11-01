<?php
/**
 * Resolves which spec profile applies to a product.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Specs;

defined( 'ABSPATH' ) || exit;

/**
 * Resolution order (§11.3):
 * 1. Product override `_lpg_spec_profile_id` (ignored if not a valid profile, C2).
 * 2. Product categories, deepest first then term_id ascending; for each, walk up
 *    its ancestors; the first term with a valid `lpg_spec_profile_id` wins.
 * 3. No profile.
 */
final class ProfileResolver {

	public const OVERRIDE_META = '_lpg_spec_profile_id';

	public const TERM_META = 'lpg_spec_profile_id';

	/**
	 * Profile post ID for a product (variations resolve via their parent).
	 *
	 * @param int $product_id Product ID.
	 */
	public static function for_product( int $product_id ): ?int {
		$product_id = self::parent_id( $product_id );
		if ( ! $product_id ) {
			return null;
		}
		$category_ids = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

		return self::resolve(
			(int) get_post_meta( $product_id, self::OVERRIDE_META, true ),
			is_array( $category_ids ) ? $category_ids : array()
		);
	}

	/**
	 * Resolve from an override value and category IDs. Internal helper used for
	 * unsaved product objects (AttributeSync) and by for_product().
	 *
	 * @param int        $override     Override profile ID (0 = inherit).
	 * @param array<int> $category_ids product_cat term IDs.
	 */
	public static function resolve( int $override, array $category_ids ): ?int {
		if ( ProfileRepository::is_profile( $override ) ) {
			return $override;
		}

		return self::for_categories( $category_ids );
	}

	/**
	 * Profile from categories only (steps 2–3).
	 *
	 * @param array<int> $category_ids product_cat term IDs.
	 */
	public static function for_categories( array $category_ids ): ?int {
		$chains = array();
		foreach ( array_unique( array_map( 'intval', $category_ids ) ) as $term_id ) {
			if ( $term_id <= 0 ) {
				continue;
			}
			$ancestors = get_ancestors( $term_id, 'product_cat', 'taxonomy' );
			$chains[]  = array(
				'term_id' => $term_id,
				'depth'   => count( $ancestors ),
				'chain'   => array_merge( array( $term_id ), array_map( 'intval', $ancestors ) ),
			);
		}

		usort(
			$chains,
			static fn( array $a, array $b ): int => array( $b['depth'], $a['term_id'] ) <=> array( $a['depth'], $b['term_id'] )
		);

		foreach ( $chains as $item ) {
			foreach ( $item['chain'] as $term_id ) {
				$profile_id = (int) get_term_meta( $term_id, self::TERM_META, true );
				if ( ProfileRepository::is_profile( $profile_id ) ) {
					return $profile_id;
				}
			}
		}

		return null;
	}

	/**
	 * Parent product ID for a variation; the ID itself otherwise. 0 if not a product.
	 *
	 * @param int $product_id Product or variation ID.
	 */
	public static function parent_id( int $product_id ): int {
		$post = get_post( $product_id );
		if ( ! $post instanceof \WP_Post ) {
			return 0;
		}
		if ( 'product_variation' === $post->post_type ) {
			return (int) $post->post_parent;
		}

		return 'product' === $post->post_type ? $product_id : 0;
	}
}
