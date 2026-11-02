<?php
/**
 * Shipping rate display tweaks.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Commerce;

use LPG\Commerce\Service;
use LPG\Commerce\Settings\Settings;
use LPG\Commerce\WhatsApp\MessageBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * C23 / DEF-011, display only (zone settings are untouched):
 * - when a free_shipping rate applies, paid flat_rate rates are hidden
 *   (local pickup always stays);
 * - a Kenyan address outside the shipping zones gets a friendly "Nairobi
 *   only, contact us on WhatsApp" note instead of WooCommerce's generic
 *   "no shipping options" text.
 *
 * The note uses the classic cart/checkout filters. The block checkout renders
 * its "no shipping options" text in JavaScript with no server-side filter in
 * WooCommerce 11.2, so it is not covered here (see the 5C/C23 report).
 */
final class ShippingDisplay implements Service {

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_package_rates', array( self::class, 'hide_paid_when_free' ), 100 );
		add_filter( 'woocommerce_no_shipping_available_html', array( self::class, 'no_shipping_html' ) );
		add_filter( 'woocommerce_cart_no_shipping_available_html', array( self::class, 'no_shipping_html' ) );
	}

	/**
	 * Remove flat_rate rates when any free_shipping rate is present.
	 *
	 * @param array<string, \WC_Shipping_Rate> $rates Package rates.
	 * @return array<string, \WC_Shipping_Rate>
	 */
	public static function hide_paid_when_free( $rates ) {
		if ( ! is_array( $rates ) ) {
			return $rates;
		}
		$has_free = false;
		foreach ( $rates as $rate ) {
			if ( $rate instanceof \WC_Shipping_Rate && 'free_shipping' === $rate->get_method_id() ) {
				$has_free = true;
				break;
			}
		}
		if ( ! $has_free ) {
			return $rates;
		}
		return array_filter(
			$rates,
			static fn( $rate ) => ! ( $rate instanceof \WC_Shipping_Rate && 'flat_rate' === $rate->get_method_id() )
		);
	}

	/**
	 * Friendly note for Kenyan addresses with no rates; other countries keep
	 * WooCommerce's text.
	 *
	 * @param string $html Default HTML.
	 * @return string
	 */
	public static function no_shipping_html( $html ) {
		$country = function_exists( 'WC' ) && WC()->customer ? WC()->customer->get_shipping_country() : '';
		if ( 'KE' !== $country ) {
			return $html;
		}
		return self::message_html();
	}

	/**
	 * The note: text, plus a WhatsApp link when a number is configured.
	 */
	public static function message_html(): string {
		$text   = esc_html__( 'We currently deliver within Nairobi only. For other locations, contact us on WhatsApp.', 'lpg-commerce-core' );
		$number = (string) Settings::get( 'whatsapp_number' );
		$link   = preg_match( Settings::PHONE_PATTERN, $number )
			? MessageBuilder::link( $number, __( 'Hello, I would like a delivery outside Nairobi.', 'lpg-commerce-core' ) )
			: '';
		if ( '' === $link ) {
			return '<span class="lpg-no-shipping">' . $text . '</span>';
		}
		return sprintf(
			'<span class="lpg-no-shipping">%1$s <a class="lpg-no-shipping__link lpg-whatsapp-link" href="%2$s" target="_blank" rel="noopener noreferrer">%3$s<span class="screen-reader-text"> %4$s</span></a></span>',
			$text,
			esc_url( $link ),
			esc_html__( 'Message us on WhatsApp', 'lpg-commerce-core' ),
			esc_html__( '(opens in a new tab)', 'lpg-commerce-core' )
		);
	}
}
