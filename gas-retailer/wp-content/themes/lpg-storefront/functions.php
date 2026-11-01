<?php
/**
 * LPG Storefront theme setup.
 *
 * Presentation only: theme supports, pattern registration and asset enqueueing. All business
 * logic lives in the lpg-commerce-core plugin.
 *
 * Notes:
 * - Block themes get post-thumbnails, responsive-embeds, editor-styles,
 *   html5 and automatic-feed-links from core automatically.
 * - WooCommerce treats any block theme as supported
 *   (wc_current_theme_supports_woocommerce_or_fse()), so
 *   add_theme_support( 'woocommerce' ) is not needed.
 * - Styles come from theme.json, plus two small component stylesheets
 *   that load only where their blocks render: assets/css/lpg-blocks.css
 *   (lpg-commerce-core blocks) and assets/css/product-card.css (product
 *   cards).
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
 * Registers the theme's component stylesheets so each loads only when a block
 * that needs it renders.
 *
 * The core function wp_enqueue_block_style() hooks `render_block` on the front end when block
 * assets load on demand (the default for block themes), so a page without the
 * block gets no stylesheet. Small files are inlined by core because `path` is
 * set. In the editor the styles load through `enqueue_block_assets`.
 *
 * @return void
 */
function lpg_storefront_enqueue_block_styles() {
	$styles = array(
		'lpg-storefront-blocks'       => array(
			'file'   => 'assets/css/lpg-blocks.css',
			'blocks' => array( 'lpg/spec-table', 'lpg/product-documents', 'lpg/compatible-products' ),
		),
		'lpg-storefront-product-card' => array(
			'file'   => 'assets/css/product-card.css',
			'blocks' => array( 'woocommerce/product-template', 'lpg/compatible-products' ),
		),
	);

	foreach ( $styles as $handle => $style ) {
		$path = get_theme_file_path( $style['file'] );
		$args = array(
			'handle' => $handle,
			'src'    => get_theme_file_uri( $style['file'] ),
			'path'   => $path,
			'ver'    => file_exists( $path ) ? (string) filemtime( $path ) : wp_get_theme()->get( 'Version' ),
		);

		foreach ( $style['blocks'] as $block_name ) {
			wp_enqueue_block_style( $block_name, $args );
		}
	}
}
add_action( 'init', 'lpg_storefront_enqueue_block_styles' );
