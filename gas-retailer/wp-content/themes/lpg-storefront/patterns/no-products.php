<?php
/**
 * Title: No products found
 * Slug: lpg-storefront/no-products
 * Categories: lpg-storefront
 * Inserter: no
 * Description: Empty state for the shop, category, segment and brand archives (no products, or none match the filters).
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;

$lpg_shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
?>
<!-- wp:group {"className":"lpg-empty-state","layout":{"type":"default"}} -->
<div class="wp-block-group lpg-empty-state">
	<!-- wp:heading {"fontSize":"large"} -->
	<h2 class="wp-block-heading has-large-font-size"><?php echo esc_html__( 'No products found', 'lpg-storefront' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph -->
	<p><?php echo esc_html__( 'There are no products here yet, or none match the filters you selected. Try removing a filter or browse the full catalogue.', 'lpg-storefront' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons -->
	<div class="wp-block-buttons">
		<!-- wp:button {"className":"is-style-outline"} -->
		<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $lpg_shop_url ); ?>"><?php echo esc_html__( 'Browse all products', 'lpg-storefront' ); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
