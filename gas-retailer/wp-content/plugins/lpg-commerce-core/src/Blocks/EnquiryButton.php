<?php
/**
 * Renderer for the `lpg/enquiry-button` block.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Blocks;

use LPG\Commerce\Commerce\EnquiryOnly;
use LPG\Commerce\Settings\Settings;
use LPG\Commerce\WhatsApp\MessageBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * §13.6 / C19: "Enquire on WhatsApp" with an inline, editable message.
 *
 * Without JS the button is a plain link to the server-built wa.me URL. With
 * JS it opens a panel; "Open WhatsApp" logs the enquiry (best effort, no
 * message text) and opens a wa.me link built in the browser from the edited
 * text. Nothing is rendered without a WhatsApp number or a viewable product.
 */
final class EnquiryButton {

	public const NAME = 'lpg/enquiry-button';

	public const SHOW_FOR = array( 'enquiry-only', 'all' );

	/**
	 * Render the block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param int|null             $product_id Product ID from block context.
	 */
	public static function render( array $attributes, ?int $product_id ): string {
		$number  = Contact::whatsapp_number();
		$product = Markup::product( $product_id );
		if ( '' === $number || ! $product ) {
			return '';
		}
		$show_for = in_array( $attributes['showFor'] ?? '', self::SHOW_FOR, true ) ? $attributes['showFor'] : 'enquiry-only';
		if ( 'enquiry-only' === $show_for && ! EnquiryOnly::is_enquiry_only( $product ) ) {
			return '';
		}

		$message = MessageBuilder::build(
			(string) Settings::get( 'wa_template_product' ),
			array(
				'product' => $product->get_name(),
				'sku'     => $product->get_sku(),
				'url'     => (string) get_permalink( $product->get_id() ),
			)
		);
		$href    = MessageBuilder::link( $number, $message );
		$id      = wp_unique_id( 'lpg-enquiry-' );

		Registry::enqueue_view_script( self::NAME );

		return sprintf(
			'<div %1$s data-lpg-number="%2$s" data-lpg-product="%3$d" %4$s>'
			. '<a class="lpg-enquiry__toggle wp-element-button" href="%5$s" data-lpg-panel="%6$s-panel">%7$s</a>'
			. '<div class="lpg-enquiry__panel" id="%6$s-panel" hidden>'
			. '<label class="lpg-enquiry__label" for="%6$s-message">%8$s</label>'
			. '<textarea class="lpg-enquiry__message" id="%6$s-message" rows="4" maxlength="%9$d">%10$s</textarea>'
			. '<p class="lpg-notice lpg-enquiry__notice">%11$s%12$s</p>'
			. '<a class="lpg-enquiry__open wp-element-button" href="%5$s" target="_blank" rel="noopener noreferrer">%13$s<span class="screen-reader-text"> %14$s</span></a>'
			. '</div></div>',
			Markup::wrapper( self::NAME, 'lpg-enquiry' ),
			esc_attr( $number ),
			$product->get_id(),
			Contact::rest_attributes( 'enquiry' ),
			esc_url( $href ),
			esc_attr( $id ),
			esc_html__( 'Enquire on WhatsApp', 'lpg-commerce-core' ),
			esc_html__( 'Your message (you can edit it)', 'lpg-commerce-core' ),
			MessageBuilder::MAX_LENGTH,
			esc_textarea( $message ),
			Contact::draft_flag(),
			Contact::notice_text( 'product' ),
			esc_html__( 'Open WhatsApp', 'lpg-commerce-core' ),
			esc_html__( '(opens in a new tab)', 'lpg-commerce-core' )
		);
	}
}
