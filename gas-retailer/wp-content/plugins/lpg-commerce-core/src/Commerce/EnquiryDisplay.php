<?php
/**
 * Front-end display of enquiry-only products: "Enquire" label and a
 * "View details" card button.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Commerce;

use LPG\Commerce\Service;

defined( 'ABSPATH' ) || exit;

/**
 * §12.1 (owner proxy P-015). Display only: the stored price is never changed,
 * and admin screens, REST, the Store API and product JSON-LD are untouched.
 */
final class EnquiryDisplay implements Service {

	/**
	 * Depth of REST requests currently being dispatched (covers internal
	 * rest_do_request() calls, where REST_REQUEST is not defined).
	 *
	 * @var int
	 */
	private int $rest_depth = 0;

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_get_price_html', array( $this, 'filter_price_html' ), 100, 2 );
		add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'filter_loop_button' ), 100, 2 );
		add_filter( 'render_block_woocommerce/product-button', array( $this, 'filter_product_button_block' ), 100, 3 );

		add_filter( 'rest_request_before_callbacks', array( $this, 'enter_rest' ), -1000 );
		add_filter( 'rest_request_after_callbacks', array( $this, 'leave_rest' ), PHP_INT_MAX );
	}

	/**
	 * Replace the price HTML with the "Enquire" label.
	 *
	 * @param string      $price_html Price HTML.
	 * @param \WC_Product $product    Product or variation.
	 * @return string
	 */
	public function filter_price_html( $price_html, $product ) {
		if ( ! $product instanceof \WC_Product || ! $this->is_front_end() || ! EnquiryOnly::is_enquiry_only( $product ) ) {
			return $price_html;
		}

		return self::label_html();
	}

	/**
	 * Classic loops: replace the add-to-cart / "Read more" link.
	 *
	 * @param string      $link    Link HTML.
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	public function filter_loop_button( $link, $product ) {
		if ( ! $product instanceof \WC_Product || ! $this->is_front_end() || ! EnquiryOnly::is_enquiry_only( $product ) ) {
			return $link;
		}

		return self::link_html( $product );
	}

	/**
	 * Block themes: replace the `woocommerce/product-button` output.
	 *
	 * The product comes from the block context `postId`, falling back to the
	 * global $product, exactly as WooCommerce's ProductButton::render() does.
	 *
	 * @param string         $block_content Rendered block.
	 * @param array          $parsed_block  Parsed block.
	 * @param \WP_Block|null $instance      Block instance.
	 * @return string
	 */
	public function filter_product_button_block( $block_content, $parsed_block, $instance = null ) {
		// WooCommerce renders nothing here (e.g. inside the add-to-cart form for a non-purchasable product): keep it.
		if ( '' === trim( (string) $block_content ) || ! $this->is_front_end() ) {
			return $block_content;
		}

		$product = null;
		$post_id = ( $instance instanceof \WP_Block && isset( $instance->context['postId'] ) ) ? (int) $instance->context['postId'] : 0;
		if ( $post_id ) {
			$product = wc_get_product( $post_id );
		}
		if ( ! $product instanceof \WC_Product && isset( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof \WC_Product ) {
			$product = $GLOBALS['product'];
		}

		if ( ! $product instanceof \WC_Product || ! EnquiryOnly::is_enquiry_only( $product ) ) {
			return $block_content;
		}

		return self::link_html( $product );
	}

	/**
	 * REST dispatch started.
	 *
	 * @param mixed $response Response (passed through).
	 * @return mixed
	 */
	public function enter_rest( $response ) {
		++$this->rest_depth;
		return $response;
	}

	/**
	 * REST dispatch finished.
	 *
	 * @param mixed $response Response (passed through).
	 * @return mixed
	 */
	public function leave_rest( $response ) {
		$this->rest_depth = max( 0, $this->rest_depth - 1 );
		return $response;
	}

	/**
	 * The "Enquire" label markup.
	 */
	public static function label_html(): string {
		return '<span class="lpg-enquire-label">' . esc_html__( 'Enquire', 'lpg-commerce-core' ) . '</span>';
	}

	/**
	 * The "View details" link markup.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function link_html( \WC_Product $product ): string {
		return sprintf(
			'<a class="lpg-enquire-link wp-element-button is-style-outline" href="%1$s">%2$s</a>',
			esc_url( $product->get_permalink() ),
			esc_html__( 'View details', 'lpg-commerce-core' )
		);
	}

	/**
	 * Whether this request renders the storefront (not admin, REST or Store API).
	 */
	private function is_front_end(): bool {
		if ( $this->rest_depth > 0 || wp_is_serving_rest_request() ) {
			return false;
		}

		if ( function_exists( 'WC' ) && ( WC()->is_rest_api_request() || WC()->is_store_api_request() ) ) {
			return false;
		}

		if ( is_admin() ) {
			// admin-ajax is front end only when called from a front-end page (e.g. not quick edit in the product list).
			if ( ! wp_doing_ajax() ) {
				return false;
			}
			$referer = (string) wp_get_raw_referer();
			if ( '' === $referer ) {
				return false;
			}
			// Compare paths, not full URLs, so an http/https mismatch behind a proxy can't misclassify admin screens.
			$referer_path = (string) wp_parse_url( $referer, PHP_URL_PATH );
			$admin_path   = (string) wp_parse_url( admin_url(), PHP_URL_PATH );
			if ( '' !== $admin_path && 0 === strpos( $referer_path, $admin_path ) ) {
				return false;
			}
		}

		return true;
	}
}
