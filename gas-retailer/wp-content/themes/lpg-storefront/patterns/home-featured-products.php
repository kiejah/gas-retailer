<?php
/**
 * Title: Homepage: featured products
 * Slug: lpg-storefront/home-featured-products
 * Categories: lpg-storefront
 * Description: Grid of up to four featured products. Hidden when no product is marked as featured.
 *
 * The heading sits inside the Product Collection block. WooCommerce renders
 * nothing for a Product Collection that has no products and no "No results"
 * block, so with zero matching products the whole section, heading included,
 * is hidden (P-005).
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:woocommerce/product-collection {"queryId":11,"query":{"perPage":4,"pages":1,"offset":0,"postType":"product","order":"desc","orderBy":"date","search":"","exclude":[],"inherit":false,"taxQuery":{},"isProductCollectionBlock":true,"featured":true,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","outofstock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[],"filterable":false},"tagName":"div","displayLayout":{"type":"flex","columns":4,"shrinkColumns":true},"dimensions":{"widthType":"fill"},"queryContextIncludes":["collection"],"align":"wide","className":"lpg-product-grid"} -->
<div class="wp-block-woocommerce-product-collection alignwide lpg-product-grid">
	<!-- wp:heading {"className":"lpg-section__title"} -->
	<h2 class="wp-block-heading lpg-section__title"><?php echo esc_html__( 'Featured products', 'lpg-storefront' ); ?></h2>
	<!-- /wp:heading -->

		<!-- wp:woocommerce/product-template -->
			<!-- wp:woocommerce/product-image {"showSaleBadge":false,"imageSizing":"thumbnail","isDescendentOfQueryLoop":true} -->
				<!-- wp:woocommerce/product-sale-badge {"isDescendentOfQueryLoop":true,"align":"right"} /-->
			<!-- /wp:woocommerce/product-image -->
			<!-- wp:post-title {"level":3,"isLink":true,"fontSize":"medium","__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->
			<!-- wp:post-terms {"term":"product_brand","className":"lpg-card__brand"} /-->
			<!-- wp:woocommerce/product-sku {"className":"lpg-card__sku"} /-->
			<!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true} /-->
			<!-- wp:woocommerce/product-button {"isDescendentOfQueryLoop":true} /-->
		<!-- /wp:woocommerce/product-template -->
</div>
<!-- /wp:woocommerce/product-collection -->
