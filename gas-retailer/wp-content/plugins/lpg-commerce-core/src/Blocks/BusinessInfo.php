<?php
/**
 * Renderer for the `lpg/business-info` block.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Blocks;

use LPG\Commerce\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * §13.6: address, opening hours and phone from the LPG settings, line breaks
 * kept. Placeholders (`[Shop address, Nairobi]`) render as they are. Empty
 * parts are skipped; nothing renders when all are empty. No JavaScript.
 */
final class BusinessInfo {

	public const NAME = 'lpg/business-info';

	/**
	 * Render the block.
	 *
	 * @param array<string, mixed> $attributes Block attributes (unused).
	 */
	public static function render( array $attributes = array() ): string {
		unset( $attributes );
		$parts   = array();
		$address = trim( (string) Settings::get( 'shop_address' ) );
		$hours   = trim( (string) Settings::get( 'opening_hours' ) );
		$phone   = Contact::phone();

		if ( '' !== $address ) {
			$parts[] = self::part( 'address', __( 'Address', 'lpg-commerce-core' ), nl2br( esc_html( $address ), false ) );
		}
		if ( '' !== $hours ) {
			$parts[] = self::part( 'hours', __( 'Opening hours', 'lpg-commerce-core' ), nl2br( esc_html( $hours ), false ) );
		}
		if ( '' !== $phone ) {
			$parts[] = self::part( 'phone', __( 'Phone', 'lpg-commerce-core' ), Contact::phone_link( $phone, 'lpg-phone-link' ) );
		}
		if ( ! $parts ) {
			return '';
		}

		return '<address ' . Markup::wrapper( self::NAME, 'lpg-business-info' ) . '>' . implode( '', $parts ) . '</address>';
	}

	/**
	 * One labelled part.
	 *
	 * @param string $key   Modifier.
	 * @param string $label Label.
	 * @param string $html  Escaped value HTML.
	 */
	private static function part( string $key, string $label, string $html ): string {
		return sprintf(
			'<span class="lpg-business-info__item lpg-business-info__item--%1$s"><span class="lpg-business-info__label">%2$s</span> <span class="lpg-business-info__value">%3$s</span></span>',
			esc_attr( $key ),
			esc_html( $label ),
			$html
		);
	}
}
