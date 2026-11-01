<?php
/**
 * Title: Homepage: segment tiles
 * Slug: lpg-storefront/home-segments
 * Categories: lpg-storefront
 * Description: Household, Commercial and Industrial tiles linking to the segment landing pages (/segment/<slug>/).
 *
 * Tiles are built from the lpg_segment terms that exist when the pattern
 * renders; a missing term (or an inactive LPG plugin) drops its tile.
 * Tile descriptions are PLACEHOLDER COPY.
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;

$lpg_segments = array(
	'household'  => array(
		__( 'Household', 'lpg-storefront' ),
		__( '[Placeholder] Equipment and accessories for cooking with LPG at home.', 'lpg-storefront' ),
	),
	'commercial' => array(
		__( 'Commercial', 'lpg-storefront' ),
		__( '[Placeholder] Equipment for kitchens in restaurants, hotels, schools and other businesses.', 'lpg-storefront' ),
	),
	'industrial' => array(
		__( 'Industrial', 'lpg-storefront' ),
		__( '[Placeholder] Equipment for industrial and large-volume LPG installations.', 'lpg-storefront' ),
	),
);

$lpg_tiles = array();
foreach ( $lpg_segments as $lpg_slug => $lpg_copy ) {
	$lpg_term = taxonomy_exists( 'lpg_segment' ) ? get_term_by( 'slug', $lpg_slug, 'lpg_segment' ) : false;
	$lpg_link = $lpg_term ? get_term_link( $lpg_term ) : '';
	if ( $lpg_link && ! is_wp_error( $lpg_link ) ) {
		$lpg_tiles[] = array( $lpg_copy[0], $lpg_copy[1], $lpg_link );
	}
}

if ( ! $lpg_tiles ) {
	return;
}
?>
<!-- wp:group {"tagName":"section","align":"wide","className":"lpg-section","layout":{"type":"default"}} -->
<section class="wp-block-group alignwide lpg-section">
	<!-- wp:heading {"className":"lpg-section__title"} -->
	<h2 class="wp-block-heading lpg-section__title"><?php echo esc_html__( 'Shop by segment', 'lpg-storefront' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:group {"className":"lpg-tiles","layout":{"type":"grid","columnCount":3,"minimumColumnWidth":"14rem"}} -->
	<div class="wp-block-group lpg-tiles">
		<?php foreach ( $lpg_tiles as $lpg_tile ) : ?>
		<!-- wp:group {"className":"lpg-tile","layout":{"type":"default"}} -->
		<div class="wp-block-group lpg-tile">
			<!-- wp:heading {"level":3,"className":"lpg-tile__title"} -->
			<h3 class="wp-block-heading lpg-tile__title"><a href="<?php echo esc_url( $lpg_tile[2] ); ?>"><?php echo esc_html( $lpg_tile[0] ); ?></a></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"fontSize":"small"} -->
			<p class="has-small-font-size"><?php echo esc_html( $lpg_tile[1] ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
