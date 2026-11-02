<?php
/**
 * Shared contact, notice and REST helpers for the Phase 5 blocks.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Blocks;

use LPG\Commerce\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Helpers for the enquiry, RFQ, exchange, WhatsApp-link and business-info blocks.
 */
final class Contact {

	/**
	 * The shop's WhatsApp number (digits), or '' when none or invalid.
	 */
	public static function whatsapp_number(): string {
		$number = (string) Settings::get( 'whatsapp_number' );
		return preg_match( Settings::PHONE_PATTERN, $number ) ? $number : '';
	}

	/**
	 * The call number (digits): `contact_phone`, else the WhatsApp number.
	 */
	public static function phone(): string {
		$phone = (string) Settings::get( 'contact_phone' );
		return preg_match( Settings::PHONE_PATTERN, $phone ) ? $phone : self::whatsapp_number();
	}

	/**
	 * The public contact email (`public_email`), or '' when none or invalid.
	 * Notification addresses are never shown on the front end (C22 L3).
	 */
	public static function public_email(): string {
		$email = (string) Settings::get( 'public_email' );
		return is_email( $email ) ? $email : '';
	}

	/**
	 * Human format: Kenyan 254XXXXXXXXX as "+254 7XX XXX XXX", others "+digits".
	 *
	 * @param string $digits Digits.
	 */
	public static function format_phone( string $digits ): string {
		$digits = (string) preg_replace( '/\D+/', '', $digits );
		if ( '' === $digits ) {
			return '';
		}
		if ( preg_match( '/^254(\d{3})(\d{3})(\d{3})$/', $digits, $m ) ) {
			return '+254 ' . $m[1] . ' ' . $m[2] . ' ' . $m[3];
		}
		return '+' . $digits;
	}

	/**
	 * Escaped `tel:` link.
	 *
	 * @param string $digits    Digits.
	 * @param string $css_class CSS class.
	 */
	public static function phone_link( string $digits, string $css_class ): string {
		$digits = (string) preg_replace( '/\D+/', '', $digits );
		if ( '' === $digits ) {
			return '';
		}
		return sprintf( '<a class="%s" href="%s">%s</a>', esc_attr( $css_class ), esc_url( 'tel:+' . $digits ), esc_html( self::format_phone( $digits ) ) );
	}

	/**
	 * The `data-lpg-*` attributes a form block needs to call an lpg/v1 route:
	 * the REST URL and a `wp_rest` nonce (valid for logged-out visitors as user 0).
	 *
	 * @param string $route Route, e.g. 'enquiry'.
	 */
	public static function rest_attributes( string $route ): string {
		return sprintf(
			'data-lpg-endpoint="%s" data-lpg-nonce="%s"',
			esc_url( rest_url( 'lpg/v1/' . $route ) ),
			esc_attr( wp_create_nonce( 'wp_rest' ) )
		);
	}

	/**
	 * "Privacy Policy" as a link when the privacy page is published, else plain text.
	 */
	private static function privacy_policy_html(): string {
		$label = esc_html__( 'Privacy Policy', 'lpg-commerce-core' );
		$url   = get_privacy_policy_url();
		return '' !== $url ? sprintf( '<a href="%s">%s</a>', esc_url( $url ), $label ) : $label;
	}

	/**
	 * Business name for the notices: the site name.
	 */
	private static function business_name(): string {
		return wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * The draft flag shown with every consent / notice text until the client
	 * approves the wording (W-007, P-023).
	 */
	public static function draft_flag(): string {
		return '<span class="lpg-notice__draft">' . esc_html__( '[Draft wording, pending approval]', 'lpg-commerce-core' ) . '</span> ';
	}

	/**
	 * Privacy notice text (docs/legal/privacy-notice-draft.md, Part A), escaped
	 * HTML without the wrapper. Types: product, exchange, rfq.
	 *
	 * @param string $type Notice type.
	 */
	public static function notice_text( string $type ): string {
		switch ( $type ) {
			case 'exchange':
				/* translators: 1: business name, 2: "Privacy Policy" (link). */
				$text = __( 'By continuing to WhatsApp, you share your request (cylinder brand, size and delivery location) with %1$s through WhatsApp, a service run by Meta. We use it only to arrange your exchange or refill. See our %2$s.', 'lpg-commerce-core' );
				break;
			case 'rfq':
				/* translators: 1: business name, 2: "Privacy Policy" (link). */
				$text = __( 'I agree that %1$s may use the details I\'ve provided to respond to my quote request and contact me about it, as described in the %2$s.', 'lpg-commerce-core' );
				break;
			default:
				/* translators: 1: business name, 2: "Privacy Policy" (link). */
				$text = __( 'You can edit this message before sending. Sending it shares your WhatsApp number and message with %1$s via WhatsApp (Meta). See our %2$s.', 'lpg-commerce-core' );
		}
		return sprintf( esc_html( $text ), esc_html( self::business_name() ), self::privacy_policy_html() );
	}

	/**
	 * Inline SVG chat icon (generic speech bubble, not a brand logo).
	 */
	public static function chat_icon(): string {
		return '<svg class="lpg-icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 3C6.5 3 2 6.9 2 11.7c0 2.4 1.1 4.6 3 6.2L4 21.5l4.2-1.9c1.2.4 2.5.6 3.8.6 5.5 0 10-3.9 10-8.6S17.5 3 12 3zm0 15.2c-1.2 0-2.3-.2-3.4-.6l-.4-.1-2 .9.5-1.8-.4-.3c-1.7-1.3-2.6-3.1-2.6-5 0-3.7 3.7-6.6 8.3-6.6s8.3 3 8.3 6.6-3.7 6.9-8.3 6.9z"/></svg>';
	}
}
