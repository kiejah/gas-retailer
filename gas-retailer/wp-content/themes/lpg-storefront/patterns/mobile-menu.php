<?php
/**
 * Title: Mobile menu
 * Slug: lpg-storefront/mobile-menu
 * Categories: lpg-storefront
 * Block Types: core/template-part/navigation-overlay
 * Inserter: no
 * Description: The menu shown when the header menu button is tapped below 600 px: close button, a collapsible Products list, then the segments and main categories.
 *
 * Core's default overlay always shows every submenu expanded, which pushed the
 * segments off-screen at 375 px (P-014). This custom overlay (WP 7.0+
 * `navigation-overlay` template part) uses a native <details>/<summary>
 * disclosure for Products: collapsed when the menu opens, opens on tap, Enter
 * or Space, and its expanded/collapsed state is exposed to screen readers by
 * the browser. No JavaScript is added. The items come from inc/nav-items.php,
 * the same source as the desktop menu.
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;

$lpg_nav = require get_theme_file_path( 'inc/nav-items.php' );

/**
 * Prints a core/list block of links.
 *
 * @param array  $items     Items with `label` (plain text) and `url`.
 * @param string $class_name Extra class.
 * @return void
 */
$lpg_list = static function ( $items, $class_name ) {
	echo '<!-- wp:list {"className":"' . esc_attr( $class_name ) . '"} -->' . "\n";
	echo '<ul class="wp-block-list ' . esc_attr( $class_name ) . '">';
	foreach ( $items as $item ) {
		echo '<!-- wp:list-item --><li><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a></li><!-- /wp:list-item -->';
	}
	echo "</ul>\n<!-- /wp:list -->\n";
};
?>
<!-- wp:group {"className":"lpg-mobile-menu","backgroundColor":"base","textColor":"contrast","layout":{"type":"default"}} -->
<div class="wp-block-group lpg-mobile-menu has-contrast-color has-base-background-color has-text-color has-background">
	<!-- wp:navigation-overlay-close {"className":"lpg-mobile-menu__close"} /-->

	<!-- wp:details {"className":"lpg-mobile-menu__products"} -->
	<details class="wp-block-details lpg-mobile-menu__products"><summary><?php echo esc_html__( 'Products', 'lpg-storefront' ); ?></summary>
	<?php $lpg_list( $lpg_nav['products'], 'lpg-mobile-menu__sublist' ); ?>
	</details>
	<!-- /wp:details -->

	<?php $lpg_list( $lpg_nav['top'], 'lpg-mobile-menu__list' ); ?>
</div>
<!-- /wp:group -->
