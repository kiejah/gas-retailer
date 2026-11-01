<?php
/**
 * Warranty and enquiry-only fields in the product General tab.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Commerce;

use LPG\Commerce\Service;

defined( 'ABSPATH' ) || exit;

/**
 * `_lpg_warranty` (textarea) and `_lpg_enquiry_only` (checkbox), §11.4.
 */
final class ProductFields implements Service {

	public const WARRANTY_META = '_lpg_warranty';

	public const ENQUIRY_META = '_lpg_enquiry_only';

	private const NONCE       = 'lpg_save_product_fields';
	private const NONCE_FIELD = 'lpg_product_fields_nonce';

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'handle_save' ) );
	}

	/**
	 * Render the fields.
	 */
	public function render_fields(): void {
		global $post;
		$product_id = $post instanceof \WP_Post ? (int) $post->ID : 0;
		echo '<div class="options_group">';
		wp_nonce_field( self::NONCE, self::NONCE_FIELD, false );
		woocommerce_wp_checkbox(
			array(
				'id'          => self::ENQUIRY_META,
				'label'       => __( 'Enquiry only', 'lpg-commerce-core' ),
				'description' => __( 'Hide the add-to-cart button and ask customers to enquire instead. Products without a price are always enquiry only. The price is not changed.', 'lpg-commerce-core' ),
				'value'       => 'yes' === get_post_meta( $product_id, self::ENQUIRY_META, true ) ? 'yes' : 'no',
			)
		);
		woocommerce_wp_textarea_input(
			array(
				'id'          => self::WARRANTY_META,
				'label'       => __( 'Warranty', 'lpg-commerce-core' ),
				'placeholder' => __( 'As stated by the supplier', 'lpg-commerce-core' ),
				'desc_tip'    => true,
				'description' => __( 'Enter warranty terms from supplier information only.', 'lpg-commerce-core' ),
				'value'       => (string) get_post_meta( $product_id, self::WARRANTY_META, true ),
			)
		);
		echo '</div>';
	}

	/**
	 * Save handler.
	 *
	 * @param \WC_Product $product Product.
	 */
	public function handle_save( $product ): void {
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}
		self::apply(
			$product,
			array(
				'warranty'     => isset( $_POST[ self::WARRANTY_META ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ self::WARRANTY_META ] ) ) : '',
				'enquiry_only' => isset( $_POST[ self::ENQUIRY_META ] ) && 'yes' === sanitize_key( wp_unslash( $_POST[ self::ENQUIRY_META ] ) ),
			)
		);
	}

	/**
	 * Apply values to a product object (not saved here).
	 *
	 * @param \WC_Product          $product Product.
	 * @param array<string, mixed> $data    `warranty` (string), `enquiry_only` (bool).
	 */
	public static function apply( \WC_Product $product, array $data ): void {
		$warranty = isset( $data['warranty'] ) && is_scalar( $data['warranty'] ) ? sanitize_textarea_field( (string) $data['warranty'] ) : '';
		if ( '' === $warranty ) {
			$product->delete_meta_data( self::WARRANTY_META );
		} else {
			$product->update_meta_data( self::WARRANTY_META, $warranty );
		}
		$product->update_meta_data( self::ENQUIRY_META, ! empty( $data['enquiry_only'] ) ? 'yes' : 'no' );
	}
}
