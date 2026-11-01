<?php
/**
 * Title: Homepage: trust strip
 * Slug: lpg-storefront/home-trust
 * Categories: lpg-storefront
 * Description: Structure only, with marked placeholder labels. Brands carried, standards and business details are owner items (W-005). No logos, certifications, ratings, testimonials or figures.
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;

$lpg_slots = array(
	__( '[Placeholder: brands we carry, to be confirmed]', 'lpg-storefront' ),
	__( '[Placeholder: business details, to be confirmed]', 'lpg-storefront' ),
	__( '[Placeholder: after-sales support, to be confirmed]', 'lpg-storefront' ),
);
?>
<!-- wp:group {"tagName":"section","align":"wide","className":"lpg-section lpg-trust","layout":{"type":"default"}} -->
<section class="wp-block-group alignwide lpg-section lpg-trust">
	<!-- wp:heading {"className":"screen-reader-text"} -->
	<h2 class="wp-block-heading screen-reader-text"><?php echo esc_html__( 'About the business', 'lpg-storefront' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:group {"className":"lpg-tiles","layout":{"type":"grid","columnCount":3,"minimumColumnWidth":"14rem"}} -->
	<div class="wp-block-group lpg-tiles">
		<?php foreach ( $lpg_slots as $lpg_slot ) : ?>
		<!-- wp:paragraph {"className":"lpg-tile lpg-placeholder","fontSize":"small"} -->
		<p class="lpg-tile lpg-placeholder has-small-font-size"><?php echo esc_html( $lpg_slot ); ?></p>
		<!-- /wp:paragraph -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
