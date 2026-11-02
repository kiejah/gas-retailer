<?php
/**
 * Title: Page: Contact
 * Slug: lpg-storefront/page-contact
 * Categories: lpg-storefront
 * Block Types: core/post-content
 * Post Types: page
 * Viewport Width: 1280
 * Description: Content for the contact page: business information (lpg/business-info), the WhatsApp and phone links (lpg/whatsapp-link) and a pointer to the Request a quote page.
 *
 * Contact details come from the plugin settings (WooCommerce > Settings >
 * LPG), never from the theme. Address and hours stay marked placeholders
 * until the owner confirms them (W-002). The note is PLACEHOLDER COPY.
 *
 * The quote link uses the request-a-quote page's permalink when the page
 * exists, otherwise its expected path (the Lead creates it in Phase 5C).
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;

require_once get_theme_file_path( 'inc/page-links.php' );
$lpg_rfq_url  = lpg_storefront_published_page_url( 'request-a-quote' );
$lpg_rfq_url  = $lpg_rfq_url ? $lpg_rfq_url : home_url( '/request-a-quote/' );
$lpg_rfq_note = sprintf(
	/* translators: %s: link to the Request a quote page. */
	esc_html__( 'Ordering for a business or a project? %s with your list of items and quantities.', 'lpg-storefront' ),
	'<a href="' . esc_url( $lpg_rfq_url ) . '">' . esc_html__( 'Request a quote', 'lpg-storefront' ) . '</a>'
);
?>
<!-- wp:group {"className":"lpg-contact","layout":{"type":"default"}} -->
<div class="wp-block-group lpg-contact">
	<!-- wp:lpg/business-info /-->

	<!-- wp:lpg/whatsapp-link {"showPhone":false} /-->
</div>
<!-- /wp:group -->

<!-- wp:paragraph {"className":"lpg-placeholder-tag","style":{"spacing":{"margin":{"top":"var:preset|spacing|60"}}}} -->
<p class="lpg-placeholder-tag" style="margin-top:var(--wp--preset--spacing--60)"><?php echo esc_html__( '[Placeholder copy]', 'lpg-storefront' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php echo $lpg_rfq_note; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_html__() and esc_url() above. ?></p>
<!-- /wp:paragraph -->
