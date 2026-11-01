<?php
/**
 * Title: Homepage: delivery and refills
 * Slug: lpg-storefront/home-delivery
 * Categories: lpg-storefront
 * Description: Quiet delivery strip (Nairobi, free over KSh 10,000, pickup) with a small Refills and Exchange note. Copy is placeholder-marked and the client may revise it.
 *
 * EDIT HERE: the strip is the list block named "Delivery info strip (editable)"
 * in the Site Editor's List View (class lpg-delivery-strip). Keep it in line
 * with WooCommerce > Settings > Shipping (zone "Nairobi": flat rate, free
 * shipping over KSh 10,000, local pickup; architecture §13.9). P-023: no
 * delivery-time promises and no tracking language. Refills stay a minor,
 * WhatsApp-led flow, never above the fold (P-005).
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;

// Prefer the Refills & Exchange page (slug `refills-exchange`, created by the
// Lead in Phase 5C) and fall back to the product category.
$lpg_refills_page = get_page_by_path( 'refills-exchange' );
$lpg_refills_url  = ( $lpg_refills_page && 'publish' === get_post_status( $lpg_refills_page ) ) ? get_permalink( $lpg_refills_page ) : '';
if ( ! $lpg_refills_url ) {
	$lpg_refills_term = taxonomy_exists( 'product_cat' ) ? get_term_by( 'slug', 'refills-exchange', 'product_cat' ) : false;
	$lpg_refills_url  = $lpg_refills_term ? get_term_link( $lpg_refills_term ) : '';
	if ( is_wp_error( $lpg_refills_url ) ) {
		$lpg_refills_url = '';
	}
}
?>
<!-- wp:group {"tagName":"section","align":"wide","className":"lpg-section","layout":{"type":"default"}} -->
<section class="wp-block-group alignwide lpg-section">
	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|60"}}}} -->
	<div class="wp-block-columns">
		<!-- wp:column {"width":"66.66%"} -->
		<div class="wp-block-column" style="flex-basis:66.66%">
			<!-- wp:heading {"className":"lpg-section__title"} -->
			<h2 class="wp-block-heading lpg-section__title"><?php echo esc_html__( 'Delivery', 'lpg-storefront' ); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"className":"lpg-placeholder-tag"} -->
			<p class="lpg-placeholder-tag"><?php echo esc_html__( '[Placeholder copy]', 'lpg-storefront' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:list {"metadata":{"name":"Delivery info strip (editable)"},"className":"lpg-delivery-strip"} -->
			<ul class="wp-block-list lpg-delivery-strip">
				<!-- wp:list-item -->
				<li><?php echo esc_html__( 'Delivery within Nairobi', 'lpg-storefront' ); ?></li>
				<!-- /wp:list-item -->

				<!-- wp:list-item -->
				<li><?php echo esc_html__( 'Free delivery on orders over KSh 10,000', 'lpg-storefront' ); ?></li>
				<!-- /wp:list-item -->

				<!-- wp:list-item -->
				<li><?php echo esc_html__( 'Pickup available', 'lpg-storefront' ); ?></li>
				<!-- /wp:list-item -->
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"33.33%"} -->
		<div class="wp-block-column" style="flex-basis:33.33%">
			<!-- wp:group {"className":"lpg-tile lpg-tile--note","layout":{"type":"default"}} -->
			<div class="wp-block-group lpg-tile lpg-tile--note">
				<!-- wp:heading {"level":3,"fontSize":"medium"} -->
				<h3 class="wp-block-heading has-medium-font-size"><?php echo esc_html__( 'Refills & Exchange', 'lpg-storefront' ); ?></h3>
				<!-- /wp:heading -->

				<!-- wp:paragraph {"fontSize":"small"} -->
				<p class="has-small-font-size"><?php echo esc_html__( '[Placeholder copy] Cylinder refills and exchanges are arranged separately from equipment orders, on WhatsApp.', 'lpg-storefront' ); ?></p>
				<!-- /wp:paragraph -->
				<?php if ( $lpg_refills_url ) : ?>

				<!-- wp:paragraph {"fontSize":"small"} -->
				<p class="has-small-font-size"><a href="<?php echo esc_url( $lpg_refills_url ); ?>"><?php echo esc_html__( 'Refills & Exchange', 'lpg-storefront' ); ?></a></p>
				<!-- /wp:paragraph -->
				<?php endif; ?>
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->
