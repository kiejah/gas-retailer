<?php
/**
 * Title: Homepage: hero
 * Slug: lpg-storefront/home-hero
 * Categories: lpg-storefront, banner
 * Description: Catalogue-led hero with "Browse products" and, only when the page exists, "Request a quote".
 *
 * PLACEHOLDER COPY (owner items W-001, W-006): the heading and text describe
 * what the site sells in general terms only. No imagery until real product
 * photography exists (P-004).
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;

$lpg_shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );

// "Request a quote" only when a published page with that slug exists (§12.2).
require_once get_theme_file_path( 'inc/page-links.php' );
$lpg_rfq_url = lpg_storefront_published_page_url( 'request-a-quote' );
?>
<!-- wp:group {"tagName":"section","align":"full","className":"lpg-hero","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"backgroundColor":"surface","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull lpg-hero has-surface-background-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:group {"align":"wide","layout":{"type":"constrained","contentSize":"760px","justifyContent":"left"}} -->
	<div class="wp-block-group alignwide">
		<!-- wp:paragraph {"className":"lpg-placeholder-tag"} -->
		<p class="lpg-placeholder-tag"><?php echo esc_html__( '[Placeholder copy]', 'lpg-storefront' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":1} -->
		<h1 class="wp-block-heading"><?php echo esc_html__( 'LPG equipment and accessories', 'lpg-storefront' ); ?></h1>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"fontSize":"large"} -->
		<p class="has-large-font-size"><?php echo esc_html__( 'Regulators, hoses and fittings, burners, cookers, cylinders and safety equipment for household, commercial and industrial use.', 'lpg-storefront' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:buttons -->
		<div class="wp-block-buttons">
			<!-- wp:button -->
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $lpg_shop_url ); ?>"><?php echo esc_html__( 'Browse products', 'lpg-storefront' ); ?></a></div>
			<!-- /wp:button -->
			<?php if ( $lpg_rfq_url ) : ?>

			<!-- wp:button {"className":"is-style-outline"} -->
			<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $lpg_rfq_url ); ?>"><?php echo esc_html__( 'Request a quote', 'lpg-storefront' ); ?></a></div>
			<!-- /wp:button -->
			<?php endif; ?>
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
