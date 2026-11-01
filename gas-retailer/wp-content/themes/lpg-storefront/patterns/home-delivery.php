<?php
/**
 * Title: Homepage: delivery and refills
 * Slug: lpg-storefront/home-delivery
 * Categories: lpg-storefront
 * Description: Delivery information with a small Refills and Exchange note. PLACEHOLDER: delivery areas and fees are owner items (W-003); refills stay a minor, WhatsApp-led flow added in Phase 5.
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;

$lpg_refills_term = taxonomy_exists( 'product_cat' ) ? get_term_by( 'slug', 'refills-exchange', 'product_cat' ) : false;
$lpg_refills_url  = $lpg_refills_term ? get_term_link( $lpg_refills_term ) : '';
if ( is_wp_error( $lpg_refills_url ) ) {
	$lpg_refills_url = '';
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

			<!-- wp:paragraph {"className":"lpg-placeholder"} -->
			<p class="lpg-placeholder"><?php echo esc_html__( '[PLACEHOLDER: delivery areas, delivery times and any fees will be listed here once confirmed by the owner.]', 'lpg-storefront' ); ?></p>
			<!-- /wp:paragraph -->
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
				<p class="has-small-font-size"><?php echo esc_html__( '[Placeholder] Cylinder refills and exchanges are arranged separately from equipment orders. Details will be added here.', 'lpg-storefront' ); ?></p>
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
