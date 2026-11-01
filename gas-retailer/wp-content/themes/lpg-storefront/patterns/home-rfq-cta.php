<?php
/**
 * Title: Homepage: request a quote
 * Slug: lpg-storefront/home-rfq-cta
 * Categories: lpg-storefront, call-to-action
 * Description: Call to action for commercial and industrial buyers. Links to the Request a quote page when it exists; otherwise a neutral line with no contact details.
 *
 * The button appears only when a published page with the slug
 * `request-a-quote` exists (§12.2, Phase 5 creates it). Until then the
 * section shows a neutral "Contact us" line with no phone number or address
 * (W-002). Copy is PLACEHOLDER COPY.
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;

$lpg_rfq_page = get_page_by_path( 'request-a-quote' );
$lpg_rfq_url  = ( $lpg_rfq_page && 'publish' === get_post_status( $lpg_rfq_page ) ) ? get_permalink( $lpg_rfq_page ) : '';
?>
<!-- wp:group {"tagName":"section","align":"wide","className":"lpg-section lpg-band","layout":{"type":"constrained","contentSize":"760px","justifyContent":"left"}} -->
<section class="wp-block-group alignwide lpg-section lpg-band">
	<!-- wp:paragraph {"className":"lpg-placeholder-tag"} -->
	<p class="lpg-placeholder-tag"><?php echo esc_html__( '[Placeholder copy]', 'lpg-storefront' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"className":"lpg-section__title"} -->
	<h2 class="wp-block-heading lpg-section__title"><?php echo esc_html__( 'Bulk, commercial and project orders', 'lpg-storefront' ); ?></h2>
	<!-- /wp:heading -->
	<?php if ( $lpg_rfq_url ) : ?>

	<!-- wp:paragraph -->
	<p><?php echo esc_html__( 'Buying for a business, an institution or an installation project? Send us your list and we will prepare a quotation.', 'lpg-storefront' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons -->
	<div class="wp-block-buttons">
		<!-- wp:button -->
		<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $lpg_rfq_url ); ?>"><?php echo esc_html__( 'Request a quote', 'lpg-storefront' ); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
	<?php else : ?>

	<!-- wp:paragraph -->
	<p><?php echo esc_html__( 'Contact us for bulk and project orders.', 'lpg-storefront' ); ?></p>
	<!-- /wp:paragraph -->
	<?php endif; ?>
</section>
<!-- /wp:group -->
