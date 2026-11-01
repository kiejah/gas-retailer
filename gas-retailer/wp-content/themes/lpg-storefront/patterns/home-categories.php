<?php
/**
 * Title: Homepage: shop by category
 * Slug: lpg-storefront/home-categories
 * Categories: lpg-storefront
 * Description: Links to the top-level product categories that have products (core WooCommerce Product Categories block).
 *
 * The Product Categories block lists only categories with products. The
 * section, heading included, is left out while no category has a product,
 * so the homepage never shows an empty heading (P-005).
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;

$lpg_has_categories = taxonomy_exists( 'product_cat' ) && get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'number'     => 1,
		'fields'     => 'ids',
	)
);

if ( ! $lpg_has_categories || is_wp_error( $lpg_has_categories ) ) {
	return;
}
?>
<!-- wp:group {"tagName":"section","align":"wide","className":"lpg-section","layout":{"type":"default"}} -->
<section class="wp-block-group alignwide lpg-section">
	<!-- wp:heading {"className":"lpg-section__title"} -->
	<h2 class="wp-block-heading lpg-section__title"><?php echo esc_html__( 'Shop by category', 'lpg-storefront' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:woocommerce/product-categories {"hasCount":false,"className":"lpg-category-links lpg-category-links--top"} /-->
</section>
<!-- /wp:group -->
