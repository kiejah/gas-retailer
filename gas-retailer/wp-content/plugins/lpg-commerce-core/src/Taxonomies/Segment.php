<?php
/**
 * Customer segment taxonomy (Household, Commercial, Industrial).
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Taxonomies;

use LPG\Commerce\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Registers `lpg_segment`: who a product is for (architecture §4.1, §11.2).
 */
final class Segment implements Service {

	public const TAXONOMY = 'lpg_segment';

	/**
	 * Terms seeded on install, slug => name. Stored as content, so not translated.
	 */
	public const DEFAULT_TERMS = array(
		'household'  => 'Household',
		'commercial' => 'Commercial',
		'industrial' => 'Industrial',
	);

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'init', array( self::class, 'register_taxonomy' ) );
	}

	/**
	 * Register the taxonomy with full labels. Runs on `init`.
	 */
	public static function register_taxonomy(): void {
		register_taxonomy(
			self::TAXONOMY,
			array( 'product' ),
			array_merge(
				self::args(),
				array(
					'labels' => array(
						'name'          => __( 'Segments', 'lpg-commerce-core' ),
						'singular_name' => __( 'Segment', 'lpg-commerce-core' ),
						'menu_name'     => __( 'Segments', 'lpg-commerce-core' ),
						'all_items'     => __( 'All segments', 'lpg-commerce-core' ),
						'edit_item'     => __( 'Edit segment', 'lpg-commerce-core' ),
						'view_item'     => __( 'View segment', 'lpg-commerce-core' ),
						'update_item'   => __( 'Update segment', 'lpg-commerce-core' ),
						'add_new_item'  => __( 'Add new segment', 'lpg-commerce-core' ),
						'new_item_name' => __( 'New segment name', 'lpg-commerce-core' ),
						'search_items'  => __( 'Search segments', 'lpg-commerce-core' ),
						'not_found'     => __( 'No segments found.', 'lpg-commerce-core' ),
						'back_to_items' => __( '&larr; Back to segments', 'lpg-commerce-core' ),
					),
				)
			)
		);
	}

	/**
	 * Register the taxonomy without translated labels, for use before `init`
	 * (installer), where loading translations would be too early.
	 */
	public static function register_minimal(): void {
		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			// No rewrite/query var: $wp_rewrite may not exist yet. The full
			// registration on `init` replaces this one.
			register_taxonomy(
				self::TAXONOMY,
				array( 'product' ),
				array_merge(
					self::args(),
					array(
						'rewrite'   => false,
						'query_var' => false,
					)
				)
			);
		}
	}

	/**
	 * Create the default terms if missing. Existing terms are never changed.
	 */
	public static function seed_terms(): void {
		self::register_minimal();
		foreach ( self::DEFAULT_TERMS as $slug => $name ) {
			if ( ! get_term_by( 'slug', $slug, self::TAXONOMY ) ) {
				wp_insert_term( $name, self::TAXONOMY, array( 'slug' => $slug ) );
			}
		}
	}

	/**
	 * Registration arguments shared by both registration paths.
	 *
	 * @return array<string, mixed>
	 */
	private static function args(): array {
		return array(
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array(
				'slug'       => 'segment',
				'with_front' => false,
			),
			'capabilities'      => array(
				'manage_terms' => 'manage_product_terms',
				'edit_terms'   => 'edit_product_terms',
				'delete_terms' => 'delete_product_terms',
				'assign_terms' => 'assign_product_terms',
			),
		);
	}
}
