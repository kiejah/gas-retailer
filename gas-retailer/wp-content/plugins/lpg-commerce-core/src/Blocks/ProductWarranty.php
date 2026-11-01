<?php
/**
 * Renderer for the `lpg/product-warranty` block.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Blocks;

use LPG\Commerce\Commerce\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * §12.4: the product's `_lpg_warranty` text with a label. Nothing when empty.
 */
final class ProductWarranty {

	public const NAME = 'lpg/product-warranty';

	public const DEFAULT_LABEL = 'Warranty:';

	/**
	 * Render the block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param int|null             $product_id Product ID from block context.
	 */
	public static function render( array $attributes, ?int $product_id ): string {
		$product = Markup::product( $product_id );
		if ( ! $product ) {
			return '';
		}

		$warranty = $product->get_meta( ProductFields::WARRANTY_META );
		$warranty = is_scalar( $warranty ) ? trim( (string) $warranty ) : '';
		if ( '' === $warranty ) {
			return '';
		}

		$label = self::label( $attributes );

		return sprintf(
			'<p %1$s>%2$s<span class="lpg-product-warranty__value">%3$s</span></p>',
			Markup::wrapper( self::NAME, 'lpg-product-warranty' ),
			'' !== $label ? '<span class="lpg-product-warranty__label">' . esc_html( $label ) . '</span> ' : '',
			nl2br( esc_html( $warranty ) )
		);
	}

	/**
	 * Label text: the attribute (trimmed), or the translated default when the
	 * attribute is missing or still holds the untranslated block.json default.
	 * An empty label hides the label span.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private static function label( array $attributes ): string {
		$translated = __( 'Warranty:', 'lpg-commerce-core' );
		if ( ! array_key_exists( 'label', $attributes ) || ! is_scalar( $attributes['label'] ) ) {
			return $translated;
		}
		$label = trim( (string) $attributes['label'] );

		return self::DEFAULT_LABEL === $label ? $translated : $label;
	}
}
