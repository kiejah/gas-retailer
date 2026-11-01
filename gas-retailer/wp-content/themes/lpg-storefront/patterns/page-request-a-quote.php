<?php
/**
 * Title: Page: Request a quote
 * Slug: lpg-storefront/page-request-a-quote
 * Categories: lpg-storefront
 * Block Types: core/post-content
 * Post Types: page
 * Viewport Width: 1280
 * Description: Content for the request-a-quote page: a short placeholder intro and the plugin's quote request form (lpg/rfq-form).
 *
 * The page title (H1) comes from the page template, so the pattern starts
 * below it. The intro is PLACEHOLDER COPY: no claims about prices, stock,
 * response times or delivery. The form, its consent text and its no-JS
 * fallback come from the lpg-commerce-core plugin.
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:paragraph {"className":"lpg-placeholder-tag"} -->
<p class="lpg-placeholder-tag"><?php echo esc_html__( '[Placeholder copy]', 'lpg-storefront' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size"><?php echo esc_html__( 'Buying for a business, an institution or an installation project? Tell us what you need and we will get back to you with a quotation.', 'lpg-storefront' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php echo esc_html__( 'List each item with a quantity. If you are not sure of the exact product, describe it and we will help you choose.', 'lpg-storefront' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:lpg/rfq-form /-->
