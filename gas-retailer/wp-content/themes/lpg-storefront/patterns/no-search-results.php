<?php
/**
 * Title: No search results
 * Slug: lpg-storefront/no-search-results
 * Categories: lpg-storefront
 * Inserter: no
 * Description: Empty state for product search results, with a product search field.
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;

$lpg_shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
?>
<!-- wp:group {"className":"lpg-empty-state","layout":{"type":"default"}} -->
<div class="wp-block-group lpg-empty-state">
	<!-- wp:heading {"fontSize":"large"} -->
	<h2 class="wp-block-heading has-large-font-size"><?php echo esc_html__( 'No products match your search', 'lpg-storefront' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph -->
	<p><?php echo esc_html__( 'Check the spelling, try a more general word (for example "regulator" or "hose"), or remove a filter.', 'lpg-storefront' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:search {"label":"<?php echo esc_attr_x( 'Search products', 'search field label', 'lpg-storefront' ); ?>","showLabel":false,"placeholder":"<?php echo esc_attr_x( 'Search products, e.g. regulator', 'search field placeholder', 'lpg-storefront' ); ?>","buttonText":"<?php echo esc_attr_x( 'Search', 'search button label', 'lpg-storefront' ); ?>","buttonPosition":"button-inside","buttonUseIcon":true,"query":{"post_type":"product"},"className":"lpg-empty-state__search","namespace":"woocommerce/product-search"} /-->

	<!-- wp:paragraph -->
	<p><a href="<?php echo esc_url( $lpg_shop_url ); ?>"><?php echo esc_html__( 'Browse all products', 'lpg-storefront' ); ?></a></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
