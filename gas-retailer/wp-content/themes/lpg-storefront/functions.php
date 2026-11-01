<?php
/**
 * LPG Storefront theme setup.
 *
 * Presentation only: theme supports and pattern registration. All business
 * logic lives in the lpg-commerce-core plugin.
 *
 * Notes:
 * - Block themes get post-thumbnails, responsive-embeds, editor-styles,
 *   html5 and automatic-feed-links from core automatically.
 * - WooCommerce treats any block theme as supported
 *   (wc_current_theme_supports_woocommerce_or_fse()), so
 *   add_theme_support( 'woocommerce' ) is not needed.
 * - Styles come from theme.json, plus one small stylesheet for the
 *   lpg-commerce-core blocks (assets/css/lpg-blocks.css).
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the theme's block pattern category.
 *
 * Patterns in /patterns are registered automatically by core.
 *
 * @return void
 */
function lpg_storefront_register_pattern_categories() {
	register_block_pattern_category(
		'lpg-storefront',
		array(
			'label'       => __( 'LPG Storefront', 'lpg-storefront' ),
			'description' => __( 'Layouts for the LPG Storefront theme.', 'lpg-storefront' ),
		)
	);
}
add_action( 'init', 'lpg_storefront_register_pattern_categories' );

/**
 * Enqueues base styles for the lpg-commerce-core blocks.
 *
 * Hooked to enqueue_block_assets so it loads on the front end and inside the
 * block editor canvas. Versioned by file modification time.
 *
 * @return void
 */
function lpg_storefront_enqueue_block_styles() {
	$path = get_theme_file_path( 'assets/css/lpg-blocks.css' );

	wp_enqueue_style(
		'lpg-storefront-blocks',
		get_theme_file_uri( 'assets/css/lpg-blocks.css' ),
		array(),
		file_exists( $path ) ? (string) filemtime( $path ) : wp_get_theme()->get( 'Version' )
	);
}
add_action( 'enqueue_block_assets', 'lpg_storefront_enqueue_block_styles' );
