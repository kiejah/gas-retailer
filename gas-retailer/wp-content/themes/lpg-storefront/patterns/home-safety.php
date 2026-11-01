<?php
/**
 * Title: Homepage: safety strip
 * Slug: lpg-storefront/home-safety
 * Categories: lpg-storefront
 * Description: Structure for a short LPG safety message. PLACEHOLDER: the wording is a compliance matter for the owner (W-004); no safety claims are made.
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","className":"lpg-strip","backgroundColor":"surface","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull lpg-strip has-surface-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
	<!-- wp:group {"align":"wide","layout":{"type":"constrained","contentSize":"760px","justifyContent":"left"}} -->
	<div class="wp-block-group alignwide">
		<!-- wp:heading {"className":"lpg-section__title"} -->
		<h2 class="wp-block-heading lpg-section__title"><?php echo esc_html__( 'Safety', 'lpg-storefront' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"lpg-placeholder"} -->
		<p class="lpg-placeholder"><?php echo esc_html__( '[PLACEHOLDER: safety information will be added here once the wording is supplied and approved by the owner. Nothing on this page is a safety claim.]', 'lpg-storefront' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
