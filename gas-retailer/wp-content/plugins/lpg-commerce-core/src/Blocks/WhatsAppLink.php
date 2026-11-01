<?php
/**
 * Renderer for the `lpg/whatsapp-link` block.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Blocks;

use LPG\Commerce\WhatsApp\MessageBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * §13.6 / P-017: a modest "WhatsApp us" text link (generic chat icon, never a
 * floating bubble) and, optionally, the call link. Nothing without a number.
 * No JavaScript.
 */
final class WhatsAppLink {

	public const NAME = 'lpg/whatsapp-link';

	/**
	 * Render the block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public static function render( array $attributes ): string {
		$number = Contact::whatsapp_number();
		if ( '' === $number ) {
			return '';
		}
		$show_phone = ! array_key_exists( 'showPhone', $attributes ) || (bool) $attributes['showPhone'];

		$html = sprintf(
			'<a class="lpg-whatsapp-link" href="%s">%s<span class="lpg-whatsapp-link__text">%s</span></a>',
			esc_url( MessageBuilder::link( $number, '' ) ),
			Contact::chat_icon(),
			esc_html__( 'WhatsApp us', 'lpg-commerce-core' )
		);
		if ( $show_phone ) {
			$html .= ' ' . Contact::phone_link( Contact::phone(), 'lpg-phone-link' );
		}

		return '<div ' . Markup::wrapper( self::NAME, 'lpg-contact-links' ) . '>' . $html . '</div>';
	}
}
