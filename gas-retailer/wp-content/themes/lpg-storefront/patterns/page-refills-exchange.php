<?php
/**
 * Title: Page: Refills & Exchange
 * Slug: lpg-storefront/page-refills-exchange
 * Categories: lpg-storefront
 * Block Types: core/post-content
 * Post Types: page
 * Viewport Width: 1280
 * Description: Content for the Refills & Exchange page: a short placeholder explainer (arranged on WhatsApp, Nairobi delivery), the plugin's exchange form (lpg/exchange-form) and a link to the Refills & Exchange category.
 *
 * PLACEHOLDER COPY. Facts used: the exchange is arranged on WhatsApp (§13.6)
 * and delivery is within Nairobi only (WooCommerce shipping zone "Nairobi",
 * §13.9). P-023: no delivery-time promises and no tracking language.
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;

$lpg_refills_term = taxonomy_exists( 'product_cat' ) ? get_term_by( 'slug', 'refills-exchange', 'product_cat' ) : false;
$lpg_refills_url  = $lpg_refills_term ? get_term_link( $lpg_refills_term ) : '';
if ( is_wp_error( $lpg_refills_url ) ) {
	$lpg_refills_url = '';
}
$lpg_refills_note = $lpg_refills_url ? sprintf(
	/* translators: %s: link to the Refills & Exchange product category. */
	esc_html__( 'Looking for cylinders and refill products? See %s.', 'lpg-storefront' ),
	'<a href="' . esc_url( $lpg_refills_url ) . '">' . esc_html__( 'Refills & Exchange products', 'lpg-storefront' ) . '</a>'
) : '';
?>
<!-- wp:paragraph {"className":"lpg-placeholder-tag"} -->
<p class="lpg-placeholder-tag"><?php echo esc_html__( '[Placeholder copy]', 'lpg-storefront' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php echo esc_html__( 'Cylinder refills and exchanges are arranged on WhatsApp, separately from equipment orders. Choose your cylinder brand and size below, tell us whether you are exchanging an empty cylinder or need a new one, and continue the conversation on WhatsApp.', 'lpg-storefront' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php echo esc_html__( 'Delivery is available within Nairobi only. We will confirm the details with you on WhatsApp.', 'lpg-storefront' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:lpg/exchange-form /-->
<?php if ( $lpg_refills_url ) : ?>

<!-- wp:paragraph -->
<p><?php echo $lpg_refills_note; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_html__() and esc_url() above. ?></p>
<!-- /wp:paragraph -->
<?php endif; ?>
