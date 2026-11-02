<?php
/**
 * Renderer for the `lpg/rfq-form` block.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Blocks;

use LPG\Commerce\Rest\RfqController;
use LPG\Commerce\Taxonomies\Segment;

defined( 'ABSPATH' ) || exit;

/**
 * §13.6 quote request form, posting the C19 body contract to lpg/v1/rfq.
 *
 * Items are free-text description + quantity rows (no product picker): the
 * catalogue changes, most quote requests are for project quantities or
 * items not listed, and a product list would add a query to every view.
 *
 * Progressive enhancement: the form is rendered `hidden` and the view script
 * reveals it; without JS the visitor sees the phone number and, if set, the
 * public contact email (never the notification addresses).
 */
final class RfqForm {

	public const NAME = 'lpg/rfq-form';

	/**
	 * Render the block.
	 *
	 * @param array<string, mixed> $attributes Block attributes (unused).
	 */
	public static function render( array $attributes = array() ): string {
		unset( $attributes );
		$id = wp_unique_id( 'lpg-rfq-' );

		Registry::enqueue_view_script( self::NAME );

		$html  = '<div ' . Markup::wrapper( self::NAME, 'lpg-rfq' ) . ' ' . Contact::rest_attributes( 'rfq' ) . '>';
		$html .= self::fallback();
		$html .= '<form class="lpg-rfq__form" novalidate hidden>';
		$html .= '<p class="lpg-rfq__required-note">' . esc_html__( 'Fields marked * are required.', 'lpg-commerce-core' ) . '</p>';

		$html .= '<fieldset class="lpg-rfq__group lpg-rfq__group--contact"><legend>' . esc_html__( 'Your details', 'lpg-commerce-core' ) . '</legend>';
		$html .= self::input(
			$id,
			'contact.name',
			__( 'Name', 'lpg-commerce-core' ),
			'text',
			true,
			array(
				'autocomplete' => 'name',
				'maxlength'    => '100',
			)
		);
		$html .= self::input(
			$id,
			'contact.organisation',
			__( 'Organisation', 'lpg-commerce-core' ),
			'text',
			false,
			array(
				'autocomplete' => 'organization',
				'maxlength'    => '150',
			)
		);
		$html .= self::input(
			$id,
			'contact.phone',
			__( 'Phone', 'lpg-commerce-core' ),
			'tel',
			true,
			array(
				'autocomplete' => 'tel',
				'maxlength'    => '30',
				'inputmode'    => 'tel',
			),
			__( 'Kenyan number, for example 07XX XXX XXX.', 'lpg-commerce-core' )
		);
		$html .= self::input(
			$id,
			'contact.email',
			__( 'Email', 'lpg-commerce-core' ),
			'email',
			true,
			array(
				'autocomplete' => 'email',
				'maxlength'    => '254',
			)
		);
		$html .= self::segment_select( $id );
		$html .= '</fieldset>';

		$html .= '<fieldset class="lpg-rfq__group lpg-rfq__items" id="' . esc_attr( $id ) . '-items" data-lpg-field="items"><legend>' . esc_html__( 'What do you need?', 'lpg-commerce-core' ) . '</legend>';
		$html .= '<ol class="lpg-rfq__item-list">' . self::item_row( $id, '0' ) . '</ol>';
		$html .= '<template class="lpg-rfq__item-template">' . self::item_row( $id, '__INDEX__' ) . '</template>';
		$html .= '<p class="lpg-field-error" id="' . esc_attr( $id ) . '-items-error" hidden></p>';
		$html .= sprintf( '<button type="button" class="lpg-rfq__add-item wp-element-button is-style-outline" data-lpg-max="%d">%s</button>', RfqController::MAX_ITEMS, esc_html__( 'Add another item', 'lpg-commerce-core' ) );
		$html .= '</fieldset>';

		$html .= '<fieldset class="lpg-rfq__group lpg-rfq__group--project"><legend>' . esc_html__( 'About the project', 'lpg-commerce-core' ) . '</legend>';
		$html .= self::input( $id, 'requirements', __( 'Technical requirements', 'lpg-commerce-core' ), 'textarea', false, array( 'maxlength' => '2000' ) );
		$html .= self::input( $id, 'project', __( 'Project name or type', 'lpg-commerce-core' ), 'text', false, array( 'maxlength' => '200' ) );
		$html .= self::input(
			$id,
			'location',
			__( 'Location', 'lpg-commerce-core' ),
			'text',
			false,
			array(
				'maxlength'    => '200',
				'autocomplete' => 'address-level2',
			)
		);
		$html .= self::input( $id, 'comments', __( 'Comments', 'lpg-commerce-core' ), 'textarea', false, array( 'maxlength' => '2000' ) );
		$html .= '</fieldset>';

		// Consent: unticked, required (Part A, draft until approved).
		$html .= sprintf(
			'<div class="lpg-field lpg-field--checkbox lpg-rfq__consent"><input type="checkbox" id="%1$s-consent" name="consent" value="1" required aria-required="true" data-lpg-field="consent"> <label for="%1$s-consent">%2$s%3$s <span class="lpg-field__required" aria-hidden="true">*</span></label><p class="lpg-field-error" id="%1$s-consent-error" hidden></p></div>',
			esc_attr( $id ),
			Contact::draft_flag(),
			Contact::notice_text( 'rfq' )
		);

		// Honeypot: hidden from people and assistive tech, tempting to bots.
		$html .= sprintf(
			'<div class="lpg-rfq__hp" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;"><label for="%1$s-hp">%2$s</label><input type="text" id="%1$s-hp" name="hp" value="" tabindex="-1" autocomplete="off"></div>',
			esc_attr( $id ),
			esc_html__( 'Leave this field empty', 'lpg-commerce-core' )
		);

		$html .= '<div class="lpg-rfq__status lpg-form-status" role="status" aria-live="polite" tabindex="-1"></div>';
		$html .= '<button type="submit" class="lpg-rfq__submit wp-element-button">' . esc_html__( 'Send quote request', 'lpg-commerce-core' ) . '</button>';
		$html .= '</form></div>';

		return $html;
	}

	/**
	 * No-JS fallback: phone, plus `public_email` when set.
	 */
	private static function fallback(): string {
		$phone = Contact::phone();
		$email = Contact::public_email();
		$parts = array();
		if ( '' !== $phone ) {
			$parts[] = Contact::phone_link( $phone, 'lpg-phone-link' );
		}
		if ( '' !== $email ) {
			$parts[] = sprintf( '<a class="lpg-email-link" href="%s">%s</a>', esc_url( 'mailto:' . rawurlencode( $email ) ), esc_html( $email ) );
		}
		if ( $parts ) {
			/* translators: %s: phone and/or email links. */
			$text = sprintf( esc_html__( 'To request a quote, contact us: %s', 'lpg-commerce-core' ), implode( ' &middot; ', $parts ) );
		} else {
			$text = esc_html__( 'Please enable JavaScript to use the quote request form.', 'lpg-commerce-core' );
		}
		return '<p class="lpg-rfq__fallback lpg-form-fallback">' . $text . '</p>';
	}

	/**
	 * A labelled field with an error slot.
	 *
	 * @param string                $form_id  Form ID prefix.
	 * @param string                $field    API key, e.g. `contact.email` (also the input name).
	 * @param string                $label    Label.
	 * @param string                $type     Input type, or `textarea`.
	 * @param bool                  $required Required.
	 * @param array<string, string> $attrs    Extra attributes.
	 * @param string                $help     Help text.
	 */
	private static function input( string $form_id, string $field, string $label, string $type, bool $required, array $attrs = array(), string $help = '' ): string {
		$id       = $form_id . '-' . str_replace( '.', '-', $field );
		$describe = array();
		$extra    = '';
		foreach ( $attrs as $name => $value ) {
			$extra .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
		}
		if ( $required ) {
			$extra .= ' required aria-required="true"';
		}
		$help_html = '';
		if ( '' !== $help ) {
			$help_html  = '<p class="lpg-field__help" id="' . esc_attr( $id ) . '-help">' . esc_html( $help ) . '</p>';
			$describe[] = $id . '-help';
		}
		if ( $describe ) {
			$extra .= ' aria-describedby="' . esc_attr( implode( ' ', $describe ) ) . '"';
		}
		$common  = sprintf( 'id="%s" name="%s" data-lpg-field="%s"%s', esc_attr( $id ), esc_attr( $field ), esc_attr( $field ), $extra );
		$control = 'textarea' === $type ? '<textarea rows="4" ' . $common . '></textarea>' : '<input type="' . esc_attr( $type ) . '" ' . $common . '>';

		return sprintf(
			'<div class="lpg-field lpg-field--%1$s"><label for="%2$s">%3$s%4$s</label>%5$s%6$s<p class="lpg-field-error" id="%2$s-error" hidden></p></div>',
			esc_attr( 'textarea' === $type ? 'textarea' : 'text' ),
			esc_attr( $id ),
			esc_html( $label ),
			$required ? ' <span class="lpg-field__required" aria-hidden="true">*</span>' : '',
			$control,
			$help_html
		);
	}

	/**
	 * Segment select from `lpg_segment` terms.
	 *
	 * @param string $form_id Form ID prefix.
	 */
	private static function segment_select( string $form_id ): string {
		$id    = $form_id . '-segment';
		$terms = get_terms(
			array(
				'taxonomy'   => Segment::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'term_id',
			)
		);
		$opts  = '<option value="">' . esc_html__( 'Choose…', 'lpg-commerce-core' ) . '</option>';
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( $term instanceof \WP_Term ) {
				$opts .= sprintf( '<option value="%s">%s</option>', esc_attr( $term->slug ), esc_html( html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) ) );
			}
		}
		return sprintf(
			'<div class="lpg-field lpg-field--select"><label for="%1$s">%2$s <span class="lpg-field__required" aria-hidden="true">*</span></label><select id="%1$s" name="segment" data-lpg-field="segment" required aria-required="true">%3$s</select><p class="lpg-field-error" id="%1$s-error" hidden></p></div>',
			esc_attr( $id ),
			esc_html__( 'The quote is for', 'lpg-commerce-core' ),
			$opts
		);
	}

	/**
	 * One item row. `__INDEX__` rows live in a <template> for the view script.
	 *
	 * @param string $form_id Form ID prefix.
	 * @param string $index   Row index.
	 */
	private static function item_row( string $form_id, string $index ): string {
		$id = $form_id . '-item-' . $index;
		return sprintf(
			'<li class="lpg-rfq__item" data-lpg-index="%1$s">'
			. '<div class="lpg-field lpg-field--text lpg-rfq__item-description"><label for="%2$s-description">%3$s <span class="lpg-field__required" aria-hidden="true">*</span></label><input type="text" id="%2$s-description" name="items[%1$s][description]" data-lpg-field="items.%1$s.description" maxlength="500" required aria-required="true"><p class="lpg-field-error" id="%2$s-description-error" hidden></p></div>'
			. '<div class="lpg-field lpg-field--number lpg-rfq__item-qty"><label for="%2$s-qty">%4$s <span class="lpg-field__required" aria-hidden="true">*</span></label><input type="number" id="%2$s-qty" name="items[%1$s][qty]" data-lpg-field="items.%1$s.qty" min="1" max="10000" step="1" value="1" inputmode="numeric" required aria-required="true"><p class="lpg-field-error" id="%2$s-qty-error" hidden></p></div>'
			. '<button type="button" class="lpg-rfq__remove-item" hidden>%5$s</button>'
			. '</li>',
			esc_attr( $index ),
			esc_attr( $id ),
			esc_html__( 'Item description', 'lpg-commerce-core' ),
			esc_html__( 'Quantity', 'lpg-commerce-core' ),
			esc_html__( 'Remove item', 'lpg-commerce-core' )
		);
	}
}
