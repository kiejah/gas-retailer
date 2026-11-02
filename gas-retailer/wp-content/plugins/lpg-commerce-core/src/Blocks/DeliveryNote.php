<?php
/**
 * Renderer for the `lpg/delivery-note` block.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Blocks;

use LPG\Commerce\WhatsApp\MessageBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * C23 / DEF-011: a static "Nairobi only" delivery note with a WhatsApp link,
 * for the block checkout (whose "no shipping options" text cannot be changed
 * server-side). No JavaScript. The `text` attribute overrides the default
 * wording; the link is added only when a WhatsApp number is set.
 */
final class DeliveryNote {

	public const NAME = 'lpg/delivery-note';

	/**
	 * Render the block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public static function render( array $attributes ): string {
		$text = isset( $attributes['text'] ) && is_scalar( $attributes['text'] ) ? trim( (string) $attributes['text'] ) : '';
		if ( '' === $text ) {
			$text = __( 'We deliver within Nairobi only. For other locations, contact us on WhatsApp.', 'lpg-commerce-core' );
		}

		$number = Contact::whatsapp_number();
		$link   = '' !== $number ? MessageBuilder::link( $number, __( 'Hello, I would like a delivery outside Nairobi.', 'lpg-commerce-core' ) ) : '';
		$html   = esc_html( $text );
		if ( '' !== $link ) {
			$html .= sprintf(
				' <a class="lpg-delivery-note__link lpg-whatsapp-link" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s<span class="screen-reader-text"> %3$s</span></a>',
				esc_url( $link ),
				esc_html__( 'Message us on WhatsApp', 'lpg-commerce-core' ),
				esc_html__( '(opens in a new tab)', 'lpg-commerce-core' )
			);
		}

		return '<p ' . Markup::wrapper( self::NAME, 'lpg-delivery-note' ) . '>' . $html . '</p>';
	}
}
