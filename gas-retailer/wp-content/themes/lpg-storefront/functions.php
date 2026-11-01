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
 * - All styles come from theme.json, so no stylesheet is enqueued.
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
