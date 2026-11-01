<?php
/**
 * Enquiry-only rule: such products cannot be added to the cart.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Commerce;

use LPG\Commerce\Service;

defined( 'ABSPATH' ) || exit;

/**
 * §11.5, C6, C7. The stored price is never changed.
 */
final class EnquiryOnly implements Service {

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_is_purchasable', array( $this, 'filter_purchasable' ), 10, 2 );
		add_filter( 'woocommerce_variation_is_purchasable', array( $this, 'filter_purchasable' ), 10, 2 );
	}

	/**
	 * Purchasable filter.
	 *
	 * @param bool        $purchasable Current value.
	 * @param \WC_Product $product     Product or variation.
	 */
	public function filter_purchasable( $purchasable, $product ): bool {
		if ( ! $purchasable || ! $product instanceof \WC_Product ) {
			return (bool) $purchasable;
		}

		return ! self::is_enquiry_only( $product );
	}

	/**
	 * Whether a product is enquiry only: flagged `yes` (on itself or, for a
	 * variation, its parent) or its price is '' (0 is a valid price).
	 *
	 * @param \WC_Product $product Product or variation.
	 */
	public static function is_enquiry_only( \WC_Product $product ): bool {
		$flag_owner = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$flagged    = $flag_owner && 'yes' === get_post_meta( $flag_owner, ProductFields::ENQUIRY_META, true );

		/*
		 * Grouped products have no price of their own (they are packages of
		 * other products), so only the explicit flag counts for them.
		 */
		$no_price = ! $product->is_type( 'grouped' ) && '' === (string) $product->get_price( 'edit' );

		/**
		 * Filters whether a product is enquiry only.
		 *
		 * @param bool        $enquiry_only Computed value.
		 * @param \WC_Product $product      Product.
		 */
		return (bool) apply_filters( 'lpg_is_enquiry_only', $flagged || $no_price, $product );
	}
}
