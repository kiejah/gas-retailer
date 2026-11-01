<?php
/**
 * Shared helpers for the plugin's dynamic block renderers.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Product resolution, attribute clamping and wrapper markup (§11.9).
 */
final class Markup {

	/**
	 * Resolve the product to render: the block context `postId` first, then
	 * the global product (single product pages).
	 *
	 * Returns null for password-protected products until the visitor has
	 * entered the password (M1), and for products the current user may not
	 * view, e.g. drafts for logged-out visitors (H1, as WooCommerce's blocks).
	 *
	 * @param int|null $product_id Product ID from block context, if any.
	 */
	public static function product( ?int $product_id ): ?\WC_Product {
		if ( ! $product_id ) {
			$global     = $GLOBALS['product'] ?? null;
			$product_id = $global instanceof \WC_Product ? $global->get_id() : 0;
		}
		if ( $product_id <= 0 || 'product' !== get_post_type( $product_id ) ) {
			return null;
		}
		if ( post_password_required( $product_id ) ) {
			return null;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product || ! $product->is_viewable() ) {
			return null;
		}

		return $product;
	}

	/**
	 * Integer attribute clamped to a range; non-numeric values use the default.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $name       Attribute name.
	 * @param int                  $fallback   Default value.
	 * @param int                  $min        Minimum.
	 * @param int                  $max        Maximum.
	 */
	public static function int_attribute( array $attributes, string $name, int $fallback, int $min, int $max ): int {
		$value = isset( $attributes[ $name ] ) && is_numeric( $attributes[ $name ] ) ? (int) $attributes[ $name ] : $fallback;

		return max( $min, min( $max, $value ) );
	}

	/**
	 * Heading text: the attribute (trimmed), or the translated default when
	 * the attribute still holds the untranslated block.json default.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $source     Untranslated default (block.json).
	 * @param string               $translated Translated default.
	 */
	public static function heading( array $attributes, string $source, string $translated ): string {
		if ( ! array_key_exists( 'heading', $attributes ) || ! is_scalar( $attributes['heading'] ) ) {
			return $translated;
		}
		$heading = trim( (string) $attributes['heading'] );

		return $source === $heading ? $translated : $heading;
	}

	/**
	 * Escaped wrapper attributes. Inside block rendering this goes through
	 * get_block_wrapper_attributes() so block supports (align, spacing,
	 * typography) apply; otherwise (direct calls, tests) a plain class/style.
	 *
	 * @param string $block_name Block name, e.g. `lpg/spec-table`.
	 * @param string $class_name Stable BEM class, e.g. `lpg-spec-table`.
	 * @param string $style      Optional inline style (already safe CSS).
	 */
	public static function wrapper( string $block_name, string $class_name, string $style = '' ): string {
		$extra = array( 'class' => $class_name );
		if ( '' !== $style ) {
			$extra['style'] = $style;
		}

		$rendering = \WP_Block_Supports::$block_to_render ?? null;
		if ( function_exists( 'get_block_wrapper_attributes' ) && is_array( $rendering ) && ( $rendering['blockName'] ?? '' ) === $block_name ) {
			return get_block_wrapper_attributes( $extra );
		}

		$html = 'class="' . esc_attr( $class_name ) . '"';
		if ( '' !== $style ) {
			$html .= ' style="' . esc_attr( $style ) . '"';
		}

		return $html;
	}
}
