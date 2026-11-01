<?php
/**
 * Title: Related products
 * Slug: lpg-storefront/related-products
 * Categories: lpg-storefront
 * Inserter: no
 * Description: WooCommerce related products (same category or tag) in the shop's card grid: 2 columns on phones, up to 4 on wide screens (P-013 #2). Hidden when there are none.
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:woocommerce/product-collection {"queryId":21,"query":{"perPage":4,"pages":1,"offset":0,"postType":"product","order":"asc","orderBy":"title","search":"","exclude":[],"inherit":false,"taxQuery":{},"isProductCollectionBlock":true,"featured":false,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[],"filterable":false,"relatedBy":{"categories":true,"tags":true}},"tagName":"div","displayLayout":{"type":"flex","columns":4,"shrinkColumns":true},"dimensions":{"widthType":"fill"},"collection":"woocommerce/product-collection/related","hideControls":["inherit"],"queryContextIncludes":["collection"],"className":"lpg-product-grid lpg-related-products"} -->
<div class="wp-block-woocommerce-product-collection lpg-product-grid lpg-related-products">
	<!-- wp:heading {"className":"lpg-section__title"} -->
	<h2 class="wp-block-heading lpg-section__title"><?php echo esc_html__( 'Related products', 'lpg-storefront' ); ?></h2>
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
