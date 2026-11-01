<?php
/**
 * LPG Storefront theme setup.
 *
 * Presentation only: theme supports, pattern registration, asset enqueueing and
 * one WooCommerce block-template display opt-out (classic upsells). All business
 * logic lives in the lpg-commerce-core plugin.
 *
 * Notes:
 * - Block themes get post-thumbnails, responsive-embeds, editor-styles,
 *   html5 and automatic-feed-links from core automatically.
 * - WooCommerce treats any block theme as supported
 *   (wc_current_theme_supports_woocommerce_or_fse()), so
 *   add_theme_support( 'woocommerce' ) is not needed.
 * - Styles come from theme.json, plus small component stylesheets that
 *   load only where their blocks render: assets/css/lpg-blocks.css
 *   (lpg-commerce-core blocks), assets/css/product-card.css (product cards),
 *   assets/css/catalog.css (listings, filters, category links) and
 *   assets/css/single-product.css (product tabs, reviews, meta and warranty).
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
		'lpg-storefront-catalog'      => array(
			'file'   => 'assets/css/catalog.css',
			'blocks' => array( 'woocommerce/product-collection', 'woocommerce/product-filters', 'woocommerce/product-categories' ),
		),
		'lpg-storefront-single'       => array(
			'file'   => 'assets/css/single-product.css',
			'blocks' => array( 'woocommerce/product-details', 'lpg/product-warranty' ),
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

/**
 * Tells WooCommerce's block-template compatibility layer that the theme's
 * Product Collection upsells section (pattern lpg-storefront/upsell-products)
 * replaces the classic woocommerce_upsell_display() output.
 *
 * Without this, WooCommerce 11.2 still runs the classic hook after the
 * woocommerce/product-details block, so upsells would appear twice (once
 * unstyled). The filter is WooCommerce's documented way for a block template
 * to opt out of one hooked function (since 9.5). It applies only while a block
 * template renders and changes no data.
 *
 * @param array  $data       Additional hooked data.
 * @param string $class_name Compatibility class running the removal.
 * @return array
 */
function lpg_storefront_replace_classic_upsells( $data, $class_name ) {
	if ( 'SingleProductTemplateCompatibility' !== $class_name ) {
		return $data;
	}

	$data   = is_array( $data ) ? $data : array();
	$data[] = array(
		'hook'     => 'woocommerce_after_single_product_summary',
		'function' => 'woocommerce_upsell_display',
		'priority' => 15,
	);

	return $data;
}
add_filter( 'woocommerce_blocks_hook_compatibility_additional_data', 'lpg_storefront_replace_classic_upsells', 10, 2 );
