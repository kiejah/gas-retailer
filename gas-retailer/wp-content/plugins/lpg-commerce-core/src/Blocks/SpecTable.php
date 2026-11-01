<?php
/**
 * Renderer for the `lpg/spec-table` block.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Blocks;

use LPG\Commerce\Specs\ProductSpecs;

defined( 'ABSPATH' ) || exit;

/**
 * §11.9: a table of ProductSpecs::get(). Nothing when there are no specs.
 */
final class SpecTable {

	public const NAME = 'lpg/spec-table';

	public const DEFAULT_HEADING = 'Technical specifications';

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
		$specs = ProductSpecs::get( $product->get_id() );
		if ( ! $specs ) {
			return '';
		}

		$default = __( 'Technical specifications', 'lpg-commerce-core' );
		$heading = Markup::heading( $attributes, self::DEFAULT_HEADING, $default );

		$rows = '';
		foreach ( $specs as $spec ) {
			$rows .= sprintf(
				'<tr class="lpg-spec-table__row lpg-spec-table__row--%1$s"><th scope="row" class="lpg-spec-table__label">%2$s</th><td class="lpg-spec-table__value">%3$s</td></tr>',
				esc_attr( sanitize_html_class( (string) $spec['key'] ) ),
				esc_html( (string) $spec['label'] ),
				esc_html( (string) $spec['display'] )
			);
		}

		return sprintf(
			'<section %1$s>%2$s<table class="lpg-spec-table__table"><caption class="screen-reader-text">%3$s</caption><tbody>%4$s</tbody></table></section>',
			Markup::wrapper( self::NAME, 'lpg-spec-table' ),
			'' !== $heading ? '<h2 class="lpg-spec-table__heading">' . esc_html( $heading ) . '</h2>' : '',
			esc_html( '' !== $heading ? $heading : $default ),
			$rows
		);
	}
}
