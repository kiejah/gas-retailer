<?php
/**
 * Title: Footer
 * Slug: lpg-storefront/footer
 * Categories: lpg-storefront, footer
 * Block Types: core/template-part/footer
 * Inserter: no
 * Description: Site title, placeholder for business information, copyright line.
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"div","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|50"}},"elements":{"link":{"color":{"text":"var:preset|color|accent-light"}}}},"backgroundColor":"contrast","textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-base-color has-contrast-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:group {"align":"wide","layout":{"type":"default"}} -->
	<div class="wp-block-group alignwide">
		<!-- wp:site-title {"level":0} /-->

		<!-- wp:paragraph {"fontSize":"small"} -->
		<p class="has-small-font-size"><?php echo esc_html__( '[PLACEHOLDER] Business information (address, phone, opening hours) will be added here once confirmed by the owner.', 'lpg-storefront' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:separator {"backgroundColor":"steel","style":{"spacing":{"margin":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|40"}}}} -->
		<hr class="wp-block-separator has-text-color has-steel-color has-alpha-channel-opacity has-steel-background-color has-background" style="margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--40)"/>
		<!-- /wp:separator -->

		<!-- wp:paragraph {"fontSize":"small"} -->
		<p class="has-small-font-size">
		<?php
		printf(
			/* translators: 1: current year, 2: site name. */
			esc_html__( '© %1$s %2$s. All rights reserved.', 'lpg-storefront' ),
			esc_html( wp_date( 'Y' ) ),
			esc_html( get_bloginfo( 'name' ) )
		);
		?>
		</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
