<?php
/**
 * Title: Header
 * Slug: lpg-storefront/header
 * Categories: lpg-storefront, header
 * Block Types: core/template-part/header
 * Inserter: no
 * Description: Wordmark, product search (visible at every width), account, mini-cart and the primary navigation.
 *
 * Navigation is a core/navigation block with inner link blocks (no `ref`), so
 * no wp_navigation post is needed and core never creates a fallback menu.
 * Links are built from the terms that exist at render time; a missing term
 * (or an inactive LPG plugin, for segments) simply drops that item.
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;

/*
 * PLACEHOLDER (owner item W-001): "Gas-Plug" is the site title shown as a
 * text wordmark. No logo graphic until the owner supplies the real name,
 * logo and brand colours. Change the name under Settings > General.
 */

$lpg_cat_slugs = array(
	'cylinders',
	'cookers-ovens',
	'burners',
	'regulators',
	'hoses-fittings',
	'manifolds-changeover',
	'valves-piping',
	'gauges-monitoring',
	'safety-equipment',
	'lpg-accessories',
	'refills-exchange',
);

$lpg_terms = array(
	'product_cat' => array(),
	'lpg_segment' => array(),
);

$lpg_lookups = array(
	'product_cat' => $lpg_cat_slugs,
	'lpg_segment' => array( 'household', 'commercial', 'industrial' ),
);

foreach ( $lpg_lookups as $lpg_tax => $lpg_slugs ) {
	$lpg_found = taxonomy_exists( $lpg_tax ) ? get_terms(
		array(
			'taxonomy'   => $lpg_tax,
			'slug'       => $lpg_slugs,
			'hide_empty' => false,
		)
	) : array();
	foreach ( is_wp_error( $lpg_found ) ? array() : $lpg_found as $lpg_term ) {
		$lpg_link = get_term_link( $lpg_term );
		if ( ! is_wp_error( $lpg_link ) ) {
			$lpg_terms[ $lpg_tax ][ $lpg_term->slug ] = array(
				'label' => html_entity_decode( $lpg_term->name, ENT_QUOTES, 'UTF-8' ),
				'url'   => $lpg_link,
			);
		}
	}
}

/**
 * Prints a core/navigation-link block.
 *
 * @param string $label Link text (plain text).
 * @param string $url   Link URL.
 * @return void
 */
$lpg_nav_link = static function ( $label, $url ) {
	echo '<!-- wp:navigation-link ' . serialize_block_attributes( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- serialize_block_attributes() escapes for block comment delimiters.
		array(
			'label' => esc_html( $label ),
			'url'   => esc_url_raw( $url ),
		)
	) . " /-->\n";
};

$lpg_shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
$lpg_products = array(
	'label' => esc_html__( 'Products', 'lpg-storefront' ),
	'url'   => esc_url_raw( $lpg_shop_url ),
);
?>
<!-- wp:group {"tagName":"div","align":"full","className":"lpg-header","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|20"}},"border":{"bottom":{"color":"var:preset|color|border","width":"1px"}}},"backgroundColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull lpg-header has-base-background-color has-background" style="border-bottom-color:var(--wp--preset--color--border);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--20)">
	<!-- wp:group {"align":"wide","className":"lpg-header__bar","layout":{"type":"default"}} -->
	<div class="wp-block-group alignwide lpg-header__bar">
		<!-- wp:group {"className":"lpg-header__brand","layout":{"type":"default"}} -->
		<div class="wp-block-group lpg-header__brand">
			<!-- wp:site-title {"level":0} /-->
		</div>
		<!-- /wp:group -->

		<!-- wp:search {"label":"<?php echo esc_attr_x( 'Search products', 'search field label', 'lpg-storefront' ); ?>","showLabel":false,"placeholder":"<?php echo esc_attr_x( 'Search products, e.g. regulator', 'search field placeholder', 'lpg-storefront' ); ?>","buttonText":"<?php echo esc_attr_x( 'Search', 'search button label', 'lpg-storefront' ); ?>","buttonPosition":"button-inside","buttonUseIcon":true,"query":{"post_type":"product"},"className":"lpg-header__search","namespace":"woocommerce/product-search"} /-->

		<!-- wp:group {"className":"lpg-header__tools","style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
		<div class="wp-block-group lpg-header__tools">
			<!-- wp:woocommerce/customer-account {"displayStyle":"icon_only","iconClass":"wc-block-customer-account__account-icon"} /-->

			<!-- wp:woocommerce/mini-cart {"hasHiddenPrice":true} /-->
		</div>
		<!-- /wp:group -->

		<!-- wp:navigation {"metadata":{"ignoredHookedBlocks":["woocommerce/customer-account","woocommerce/mini-cart"]},"overlayMenu":"mobile","overlayBackgroundColor":"base","overlayTextColor":"contrast","className":"lpg-header__nav","ariaLabel":"<?php echo esc_attr_x( 'Primary', 'navigation landmark label', 'lpg-storefront' ); ?>","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"left"}} -->
		<!-- wp:navigation-submenu <?php echo serialize_block_attributes( $lpg_products ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- serialized block attributes. ?> -->
		<?php
		$lpg_nav_link( __( 'All products', 'lpg-storefront' ), $lpg_shop_url );
		foreach ( $lpg_cat_slugs as $lpg_slug ) {
			if ( isset( $lpg_terms['product_cat'][ $lpg_slug ] ) ) {
				$lpg_nav_link( $lpg_terms['product_cat'][ $lpg_slug ]['label'], $lpg_terms['product_cat'][ $lpg_slug ]['url'] );
			}
		}
		?>
		<!-- /wp:navigation-submenu -->
		<?php
		$lpg_top = array(
			array( 'lpg_segment', 'household', __( 'Household', 'lpg-storefront' ) ),
			array( 'lpg_segment', 'commercial', __( 'Commercial', 'lpg-storefront' ) ),
			array( 'lpg_segment', 'industrial', __( 'Industrial', 'lpg-storefront' ) ),
			array( 'product_cat', 'lpg-accessories', __( 'LPG Accessories', 'lpg-storefront' ) ),
			array( 'product_cat', 'safety-equipment', __( 'Safety Equipment', 'lpg-storefront' ) ),
			array( 'product_cat', 'refills-exchange', __( 'Refills & Exchange', 'lpg-storefront' ) ),
		);
		foreach ( $lpg_top as $lpg_item ) {
			if ( isset( $lpg_terms[ $lpg_item[0] ][ $lpg_item[1] ] ) ) {
				$lpg_nav_link( $lpg_item[2], $lpg_terms[ $lpg_item[0] ][ $lpg_item[1] ]['url'] );
			}
		}
		// The Request a quote page is created in Phase 5 (RFQ form block).
		$lpg_nav_link( __( 'Request a quote', 'lpg-storefront' ), home_url( '/request-a-quote/' ) );
		?>
		<!-- /wp:navigation -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
