<?php
/**
 * Title: Header
 * Slug: lpg-storefront/header
 * Categories: lpg-storefront, header
 * Block Types: core/template-part/header
 * Inserter: no
 * Description: Wordmark, product search (visible at every width), WhatsApp link, account, mini-cart and the primary navigation.
 *
 * Navigation is a core/navigation block with inner link blocks (no `ref`), so
 * no wp_navigation post is needed and core never creates a fallback menu.
 * The items come from inc/nav-items.php (shared with the mobile menu).
 *
 * Submenus open on click/tap (P-014): "Products" is a button with
 * aria-expanded, and the first submenu item is "All products". Below 600 px
 * the menu opens the "Mobile menu" template part (parts/mobile-menu.html)
 * instead of core's default overlay, because core's default overlay always
 * shows every submenu expanded.
 *
 * The WhatsApp link (lpg/whatsapp-link, plugin block, P-017/P-023) is a modest
 * text/icon link in the tools row, never a floating bubble. Below 600 px it
 * shows the icon only and keeps its text as the accessible name. It renders
 * nothing while no WhatsApp number is set in the plugin settings. showPhone is
 * false here (the phone is in the footer business information).
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;

/*
 * PLACEHOLDER (owner item W-001): "Gas-Plug" is the site title shown as a
 * text wordmark. No logo graphic until the owner supplies the real name,
 * logo and brand colours. Change the name under Settings > General.
 */

$lpg_nav = require get_theme_file_path( 'inc/nav-items.php' );

/**
 * Prints a core/navigation-link block.
 *
 * @param array $item Item with `label` (plain text) and `url`.
 * @return void
 */
$lpg_nav_link = static function ( $item ) {
	echo '<!-- wp:navigation-link ' . serialize_block_attributes( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- serialize_block_attributes() escapes for block comment delimiters.
		array(
			'label' => esc_html( $item['label'] ),
			'url'   => esc_url_raw( $item['url'] ),
		)
	) . " /-->\n";
};

$lpg_products = array(
	'label' => esc_html__( 'Products', 'lpg-storefront' ),
	'url'   => esc_url_raw( $lpg_nav['shop_url'] ),
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
			<!-- wp:lpg/whatsapp-link {"showPhone":false,"className":"lpg-header__whatsapp"} /-->

			<!-- wp:woocommerce/customer-account {"displayStyle":"icon_only","iconClass":"wc-block-customer-account__account-icon"} /-->

			<!-- wp:woocommerce/mini-cart {"hasHiddenPrice":true} /-->
		</div>
		<!-- /wp:group -->

		<!-- wp:navigation {"metadata":{"ignoredHookedBlocks":["woocommerce/customer-account","woocommerce/mini-cart"]},"overlayMenu":"mobile","overlay":"mobile-menu","submenuVisibility":"click","overlayBackgroundColor":"base","overlayTextColor":"contrast","className":"lpg-header__nav","ariaLabel":"<?php echo esc_attr_x( 'Primary', 'navigation landmark label', 'lpg-storefront' ); ?>","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"left"}} -->
		<!-- wp:navigation-submenu <?php echo serialize_block_attributes( $lpg_products ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- serialized block attributes. ?> -->
		<?php
		foreach ( $lpg_nav['products'] as $lpg_item ) {
			$lpg_nav_link( $lpg_item );
		}
		?>
		<!-- /wp:navigation-submenu -->
		<?php
		foreach ( $lpg_nav['top'] as $lpg_item ) {
			$lpg_nav_link( $lpg_item );
		}
		?>
		<!-- /wp:navigation -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
