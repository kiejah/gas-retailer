<?php
/**
 * Primary navigation items, shared by the header pattern (desktop menu) and
 * the mobile-menu pattern (overlay), so both list the same links (P-006).
 *
 * Links are built from the terms that exist when the pattern renders; a
 * missing term (or an inactive LPG plugin, for segments) drops its item.
 * "Request a quote" is included only when a published page with the slug
 * `request-a-quote` exists (architecture §12.2, P-013 #1). "Refills &
 * Exchange" goes to the published `refills-exchange` page when it exists,
 * otherwise to the product category (DEF-014, C23). Both checks use
 * inc/page-links.php.
 *
 * Presentation data only: no business logic.
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;

require_once get_theme_file_path( 'inc/page-links.php' );

$lpg_nav_cat_slugs = array(
	'cylinders',
	'cookers-ovens',
	'burners',
	'regulators',
	'hoses-fittings',
	'manifolds-changeover',
	'valves-piping',
	'gauges-monitoring',
	'safety-equipment',
	'lpg-accessories',
	'refills-exchange',
);

$lpg_nav_lookups = array(
	'product_cat' => $lpg_nav_cat_slugs,
	'lpg_segment' => array( 'household', 'commercial', 'industrial' ),
);

$lpg_nav_terms = array(
	'product_cat' => array(),
	'lpg_segment' => array(),
);

foreach ( $lpg_nav_lookups as $lpg_nav_tax => $lpg_nav_slugs ) {
	$lpg_nav_found = taxonomy_exists( $lpg_nav_tax ) ? get_terms(
		array(
			'taxonomy'   => $lpg_nav_tax,
			'slug'       => $lpg_nav_slugs,
			'hide_empty' => false,
		)
	) : array();
	foreach ( is_wp_error( $lpg_nav_found ) ? array() : $lpg_nav_found as $lpg_nav_term ) {
		$lpg_nav_link = get_term_link( $lpg_nav_term );
		if ( ! is_wp_error( $lpg_nav_link ) ) {
			$lpg_nav_terms[ $lpg_nav_tax ][ $lpg_nav_term->slug ] = array(
				'label' => html_entity_decode( $lpg_nav_term->name, ENT_QUOTES, 'UTF-8' ),
				'url'   => $lpg_nav_link,
			);
		}
	}
}

$lpg_nav_shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );

// Products submenu: "All products" first (P-014), then the categories.
$lpg_nav_products = array(
	array(
		'label' => __( 'All products', 'lpg-storefront' ),
		'url'   => $lpg_nav_shop_url,
	),
);
foreach ( $lpg_nav_cat_slugs as $lpg_nav_slug ) {
	if ( isset( $lpg_nav_terms['product_cat'][ $lpg_nav_slug ] ) ) {
		$lpg_nav_products[] = $lpg_nav_terms['product_cat'][ $lpg_nav_slug ];
	}
}

// Top-level items after Products, in P-006 order, with plain-noun labels.
$lpg_nav_top      = array();
$lpg_nav_top_defs = array(
	array( 'lpg_segment', 'household', __( 'Household', 'lpg-storefront' ) ),
	array( 'lpg_segment', 'commercial', __( 'Commercial', 'lpg-storefront' ) ),
	array( 'lpg_segment', 'industrial', __( 'Industrial', 'lpg-storefront' ) ),
	array( 'product_cat', 'lpg-accessories', __( 'LPG Accessories', 'lpg-storefront' ) ),
	array( 'product_cat', 'safety-equipment', __( 'Safety Equipment', 'lpg-storefront' ) ),
);
foreach ( $lpg_nav_top_defs as $lpg_nav_def ) {
	if ( isset( $lpg_nav_terms[ $lpg_nav_def[0] ][ $lpg_nav_def[1] ] ) ) {
		$lpg_nav_top[] = array(
			'label' => $lpg_nav_def[2],
			'url'   => $lpg_nav_terms[ $lpg_nav_def[0] ][ $lpg_nav_def[1] ]['url'],
		);
	}
}

// Refills & Exchange: the page with the exchange form, else the category.
$lpg_nav_refills_url = lpg_storefront_refills_url();
if ( $lpg_nav_refills_url ) {
	$lpg_nav_top[] = array(
		'label' => __( 'Refills & Exchange', 'lpg-storefront' ),
		'url'   => $lpg_nav_refills_url,
	);
}

$lpg_nav_rfq_url = lpg_storefront_published_page_url( 'request-a-quote' );
if ( $lpg_nav_rfq_url ) {
	$lpg_nav_top[] = array(
		'label' => __( 'Request a quote', 'lpg-storefront' ),
		'url'   => $lpg_nav_rfq_url,
	);
}

return array(
	'shop_url' => $lpg_nav_shop_url,
	'products' => $lpg_nav_products,
	'top'      => $lpg_nav_top,
);
