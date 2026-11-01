<?php
/**
 * Title: Product filters
 * Slug: lpg-storefront/product-filters
 * Categories: lpg-storefront
 * Inserter: no
 * Description: Native WooCommerce Product Filters: active filters, price, category, brand and one attribute filter per global product attribute (the filterable LPG specs).
 *
 * WooCommerce's own filter blocks and front-end script do all filtering; the
 * theme adds no JavaScript and no query code. Each filter hides itself when
 * the current results have no options for it, so only the specs relevant to
 * the products being listed appear. On small screens WooCommerce collapses
 * the filters behind a "Filter products" button (overlay mode "mobile").
 *
 * The attribute list is read when the pattern renders, so a new filterable
 * spec field (a new `pa_` attribute) appears without editing a template.
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;

$lpg_attributes = function_exists( 'wc_get_attribute_taxonomies' ) ? wc_get_attribute_taxonomies() : array();
?>
<!-- wp:woocommerce/product-filters {"className":"lpg-filters"} -->
<div class="wp-block-woocommerce-product-filters wc-block-product-filters lpg-filters">
	<!-- wp:heading {"className":"lpg-filters__title","fontSize":"large"} -->
	<h2 class="wp-block-heading lpg-filters__title has-large-font-size"><?php echo esc_html__( 'Filters', 'lpg-storefront' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:woocommerce/product-filter-active -->
	<div class="wp-block-woocommerce-product-filter-active">
		<!-- wp:woocommerce/product-filter-removable-chips -->
		<div class="wp-block-woocommerce-product-filter-removable-chips wc-block-product-filter-removable-chips"></div>
		<!-- /wp:woocommerce/product-filter-removable-chips -->

		<!-- wp:woocommerce/product-filter-clear-button -->
		<!-- wp:buttons {"layout":{"type":"flex"}} -->
		<div class="wp-block-buttons">
			<!-- wp:button {"className":"wc-block-product-filter-clear-button is-style-outline"} -->
			<div class="wp-block-button wc-block-product-filter-clear-button is-style-outline"><a class="wp-block-button__link wp-element-button"><?php echo esc_html__( 'Clear filters', 'lpg-storefront' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
		<!-- /wp:woocommerce/product-filter-clear-button -->
	</div>
	<!-- /wp:woocommerce/product-filter-active -->

	<!-- wp:woocommerce/product-filter-price -->
	<div class="wp-block-woocommerce-product-filter-price">
		<!-- wp:heading {"level":3,"className":"lpg-filters__heading"} -->
		<h3 class="wp-block-heading lpg-filters__heading"><?php echo esc_html__( 'Price', 'lpg-storefront' ); ?></h3>
		<!-- /wp:heading -->

		<!-- wp:woocommerce/product-filter-price-slider -->
		<div class="wp-block-woocommerce-product-filter-price-slider wc-block-product-filter-price-slider"></div>
		<!-- /wp:woocommerce/product-filter-price-slider -->
	</div>
	<!-- /wp:woocommerce/product-filter-price -->

	<!-- wp:woocommerce/product-filter-taxonomy {"taxonomy":"product_cat"} -->
	<div class="wp-block-woocommerce-product-filter-taxonomy">
		<!-- wp:heading {"level":3,"className":"lpg-filters__heading"} -->
		<h3 class="wp-block-heading lpg-filters__heading"><?php echo esc_html__( 'Category', 'lpg-storefront' ); ?></h3>
		<!-- /wp:heading -->

		<!-- wp:woocommerce/product-filter-checkbox-list -->
		<div class="wp-block-woocommerce-product-filter-checkbox-list wc-block-product-filter-checkbox-list"></div>
		<!-- /wp:woocommerce/product-filter-checkbox-list -->
	</div>
	<!-- /wp:woocommerce/product-filter-taxonomy -->

	<!-- wp:woocommerce/product-filter-taxonomy {"taxonomy":"product_brand"} -->
	<div class="wp-block-woocommerce-product-filter-taxonomy">
		<!-- wp:heading {"level":3,"className":"lpg-filters__heading"} -->
		<h3 class="wp-block-heading lpg-filters__heading"><?php echo esc_html__( 'Brand', 'lpg-storefront' ); ?></h3>
		<!-- /wp:heading -->

		<!-- wp:woocommerce/product-filter-checkbox-list -->
		<div class="wp-block-woocommerce-product-filter-checkbox-list wc-block-product-filter-checkbox-list"></div>
		<!-- /wp:woocommerce/product-filter-checkbox-list -->
	</div>
	<!-- /wp:woocommerce/product-filter-taxonomy -->
	<?php foreach ( $lpg_attributes as $lpg_attribute ) : ?>

	<!-- wp:woocommerce/product-filter-attribute <?php echo wp_json_encode( array( 'attributeId' => (int) $lpg_attribute->attribute_id ) ); ?> -->
	<div class="wp-block-woocommerce-product-filter-attribute">
		<!-- wp:heading {"level":3,"className":"lpg-filters__heading"} -->
		<h3 class="wp-block-heading lpg-filters__heading"><?php echo esc_html( $lpg_attribute->attribute_label ); ?></h3>
		<!-- /wp:heading -->

		<!-- wp:woocommerce/product-filter-checkbox-list -->
		<div class="wp-block-woocommerce-product-filter-checkbox-list wc-block-product-filter-checkbox-list"></div>
		<!-- /wp:woocommerce/product-filter-checkbox-list -->
	</div>
	<!-- /wp:woocommerce/product-filter-attribute -->
	<?php endforeach; ?>
</div>
<!-- /wp:woocommerce/product-filters -->
