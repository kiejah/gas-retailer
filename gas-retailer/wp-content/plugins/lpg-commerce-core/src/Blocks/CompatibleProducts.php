<?php
/**
 * Renderer for the `lpg/compatible-products` block.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Blocks;

use LPG\Commerce\Commerce\EnquiryOnly;
use LPG\Commerce\Relations\Compatibility;

defined( 'ABSPATH' ) || exit;

/**
 * §11.9: product cards (image, name, price or "Enquire") of compatible products.
 */
final class CompatibleProducts {

	public const NAME = 'lpg/compatible-products';

	public const DEFAULT_HEADING = 'Compatible products';

	public const DEFAULT_COLUMNS = 4;
	public const MIN_COLUMNS     = 2;
	public const MAX_COLUMNS     = 6;

	public const DEFAULT_LIMIT = 8;
	public const MIN_LIMIT     = 1;
	public const MAX_LIMIT     = 12;

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

		$columns = Markup::int_attribute( $attributes, 'columns', self::DEFAULT_COLUMNS, self::MIN_COLUMNS, self::MAX_COLUMNS );
		$limit   = Markup::int_attribute( $attributes, 'limit', self::DEFAULT_LIMIT, self::MIN_LIMIT, self::MAX_LIMIT );

		$cards = '';
		$count = 0;
		foreach ( Compatibility::get_ids( $product->get_id() ) as $id ) {
			if ( $count >= $limit ) {
				break;
			}
			$related = wc_get_product( $id );
			if ( ! $related instanceof \WC_Product ) {
				continue;
			}
			$cards .= self::card( $related );
			++$count;
		}
		if ( '' === $cards ) {
			return '';
		}

		$heading = Markup::heading( $attributes, self::DEFAULT_HEADING, __( 'Compatible products', 'lpg-commerce-core' ) );

		return sprintf(
			'<section %1$s>%2$s<ul class="lpg-compatible-products__list">%3$s</ul></section>',
			Markup::wrapper( self::NAME, 'lpg-compatible-products', '--lpg-columns:' . $columns ),
			'' !== $heading ? '<h2 class="lpg-compatible-products__heading">' . esc_html( $heading ) . '</h2>' : '',
			$cards
		);
	}

	/**
	 * One product card.
	 *
	 * @param \WC_Product $product Product.
	 */
	private static function card( \WC_Product $product ): string {
		$image_id = (int) $product->get_image_id();
		// Core builds and escapes the <img> (alt text from the attachment).
		$image = $image_id
			? wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, array( 'class' => 'lpg-compatible-products__image' ) )
			: wc_placeholder_img( 'woocommerce_thumbnail', array( 'class' => 'lpg-compatible-products__image' ) );

		if ( EnquiryOnly::is_enquiry_only( $product ) ) {
			$price = '<span class="lpg-compatible-products__price lpg-compatible-products__price--enquire">' . esc_html__( 'Enquire', 'lpg-commerce-core' ) . '</span>';
		} else {
			$price = '<span class="lpg-compatible-products__price">' . wp_kses_post( $product->get_price_html() ) . '</span>';
		}

		return sprintf(
			'<li class="lpg-compatible-products__item"><a class="lpg-compatible-products__link" href="%1$s">%2$s<span class="lpg-compatible-products__name">%3$s</span></a>%4$s</li>',
			esc_url( $product->get_permalink() ),
			(string) $image,
			esc_html( $product->get_name() ),
			$price
		);
	}
}
