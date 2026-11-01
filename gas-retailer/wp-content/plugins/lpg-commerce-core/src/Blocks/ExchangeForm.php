<?php
/**
 * Renderer for the `lpg/exchange-form` block.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Blocks;

use LPG\Commerce\Enquiries\EnquiryPostType;
use LPG\Commerce\Settings\Settings;
use LPG\Commerce\Taxonomies\CylinderBrands;
use LPG\Commerce\WhatsApp\MessageBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * §13.6 cylinder refill / exchange form: brand → size (filtered by brand) →
 * exchange or new → location → "Continue on WhatsApp".
 *
 * The view script POSTs lpg/v1/enquiry and opens the returned `whatsapp_url`;
 * if that is empty or the request fails, it builds the link in the browser
 * from the template and number in the data attributes. Without JS the form
 * stays hidden and a plain WhatsApp link is shown. Nothing renders without
 * a WhatsApp number or without brands.
 */
final class ExchangeForm {

	public const NAME = 'lpg/exchange-form';

	/**
	 * Render the block.
	 *
	 * @param array<string, mixed> $attributes Block attributes (unused).
	 */
	public static function render( array $attributes = array() ): string {
		unset( $attributes );
		$number = Contact::whatsapp_number();
		$brands = CylinderBrands::all();
		if ( '' === $number || ! $brands ) {
			return '';
		}
		$id = wp_unique_id( 'lpg-exchange-' );

		Registry::enqueue_view_script( self::NAME );

		$type_labels = array();
		foreach ( EnquiryPostType::EXCHANGE_TYPES as $type ) {
			$type_labels[ $type ] = EnquiryPostType::exchange_type_label( $type );
		}

		$brand_options = '<option value="">' . esc_html__( 'Choose a brand…', 'lpg-commerce-core' ) . '</option>';
		foreach ( $brands as $brand ) {
			$brand_options .= sprintf(
				'<option value="%s" data-lpg-name="%s" data-lpg-sizes="%s">%s</option>',
				esc_attr( $brand['slug'] ),
				esc_attr( $brand['name'] ),
				esc_attr( (string) wp_json_encode( array_values( $brand['sizes'] ) ) ),
				esc_html( $brand['name'] )
			);
		}

		$fallback = sprintf(
			/* translators: %s: WhatsApp link. */
			esc_html__( 'For a refill or exchange, message us on %s.', 'lpg-commerce-core' ),
			sprintf( '<a class="lpg-whatsapp-link" href="%s">%s</a>', esc_url( MessageBuilder::link( $number, '' ) ), esc_html__( 'WhatsApp', 'lpg-commerce-core' ) )
		);

		return sprintf(
			'<div %1$s data-lpg-number="%2$s" data-lpg-template="%3$s" data-lpg-types="%4$s" %5$s>'
			. '<p class="lpg-exchange__fallback lpg-form-fallback">%6$s</p>'
			. '<form class="lpg-exchange__form" novalidate hidden>'
			. '<div class="lpg-field lpg-field--select"><label for="%7$s-brand">%8$s</label><select id="%7$s-brand" name="brand" data-lpg-field="brand" required aria-required="true">%9$s</select><p class="lpg-field-error" id="%7$s-brand-error" hidden></p></div>'
			. '<div class="lpg-field lpg-field--select"><label for="%7$s-size">%10$s</label><select id="%7$s-size" name="size" data-lpg-field="size" required aria-required="true" disabled><option value="">%11$s</option></select><p class="lpg-field-error" id="%7$s-size-error" hidden></p></div>'
			. '<fieldset class="lpg-field lpg-field--radio" id="%7$s-exchange_type" data-lpg-field="exchange_type"><legend>%12$s</legend>'
			. '<label><input type="radio" name="exchange_type" value="exchange" checked> %13$s</label>'
			. '<label><input type="radio" name="exchange_type" value="new"> %14$s</label>'
			. '<p class="lpg-field-error" id="%7$s-exchange_type-error" hidden></p></fieldset>'
			. '<div class="lpg-field lpg-field--text"><label for="%7$s-location">%15$s</label><input type="text" id="%7$s-location" name="location" data-lpg-field="location" maxlength="200" autocomplete="address-level2" required aria-required="true"><p class="lpg-field-error" id="%7$s-location-error" hidden></p></div>'
			. '<p class="lpg-notice lpg-exchange__notice">%16$s%17$s</p>'
			. '<div class="lpg-exchange__status lpg-form-status" role="status" aria-live="polite" tabindex="-1"></div>'
			. '<button type="submit" class="lpg-exchange__submit wp-element-button">%18$s</button>'
			. '</form></div>',
			Markup::wrapper( self::NAME, 'lpg-exchange' ),
			esc_attr( $number ),
			esc_attr( (string) Settings::get( 'wa_template_exchange' ) ),
			esc_attr( (string) wp_json_encode( $type_labels ) ),
			Contact::rest_attributes( 'enquiry' ),
			$fallback,
			esc_attr( $id ),
			esc_html__( 'Cylinder brand', 'lpg-commerce-core' ),
			$brand_options,
			esc_html__( 'Size', 'lpg-commerce-core' ),
			esc_html__( 'Choose a brand first', 'lpg-commerce-core' ),
			esc_html__( 'What do you need?', 'lpg-commerce-core' ),
			esc_html__( 'Refill / exchange my empty cylinder', 'lpg-commerce-core' ),
			esc_html__( 'Buy a new cylinder', 'lpg-commerce-core' ),
			esc_html__( 'Delivery location', 'lpg-commerce-core' ),
			Contact::draft_flag(),
			Contact::notice_text( 'exchange' ),
			esc_html__( 'Continue on WhatsApp', 'lpg-commerce-core' )
		);
	}
}
