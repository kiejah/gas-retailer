<?php
/**
 * Phase 5 blocks: enquiry button, RFQ form, exchange form, WhatsApp link,
 * business info (§13.6, C19).
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgRecordFixtures.php';

use LPG\Commerce\Blocks\BusinessInfo;
use LPG\Commerce\Blocks\Contact;
use LPG\Commerce\Blocks\EnquiryButton;
use LPG\Commerce\Blocks\ExchangeForm;
use LPG\Commerce\Blocks\RfqForm;
use LPG\Commerce\Blocks\WhatsAppLink;
use LPG\Commerce\Taxonomies\CylinderBrand;
use LPG\Commerce\Taxonomies\Segment;
use LPG\Commerce\WhatsApp\MessageBuilder;

/**
 * Renderer markup, empty states, escaping and script loading.
 */
class Phase5BlocksTest extends WP_UnitTestCase {

	use LpgRecordFixtures;

	public function set_up(): void {
		parent::set_up();
		$this->boot_records();
		foreach ( array( 'enquiry-button', 'rfq-form', 'exchange-form' ) as $name ) {
			wp_dequeue_script( 'lpg-' . $name . '-view-script' );
		}
	}

	public function tear_down(): void {
		unset( $GLOBALS['product'] );
		delete_option( 'wp_page_for_privacy_policy' );
		$this->shutdown_records();
		parent::tear_down();
	}

	private function priced_product(): int {
		$product = new WC_Product_Simple();
		$product->set_name( '[QA TEST] Priced burner' );
		$product->set_regular_price( '1500' );
		$product->set_status( 'publish' );
		return $product->save();
	}

	private function attr( string $html, string $name ): string {
		$this->assertMatchesRegularExpression( '/' . preg_quote( $name, '/' ) . '="([^"]*)"/', $html );
		preg_match( '/' . preg_quote( $name, '/' ) . '="([^"]*)"/', $html, $m );
		return html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
	}

	// ----- Shared ----------------------------------------------------------------------

	public function test_phone_format_and_view_scripts_detached_from_block_types(): void {
		$this->assertSame( '+254 700 000 001', Contact::format_phone( '254700000001' ) );
		$this->assertSame( '+14155550100', Contact::format_phone( '1 415 555 0100' ) );
		$this->assertSame( '', Contact::format_phone( 'none' ) );

		$registry = WP_Block_Type_Registry::get_instance();
		foreach ( array( 'lpg/enquiry-button', 'lpg/rfq-form', 'lpg/exchange-form', 'lpg/whatsapp-link', 'lpg/business-info' ) as $name ) {
			$this->assertTrue( $registry->is_registered( $name ), $name );
			$this->assertSame( array(), $registry->get_registered( $name )->view_script_handles, $name );
		}
	}

	public function test_view_script_loads_only_when_markup_renders(): void {
		$handle = 'lpg-exchange-form-view-script';
		$this->assertTrue( wp_script_is( $handle, 'registered' ), 'Built view script is registered.' );

		$this->set_settings( array( 'whatsapp_number' => '' ) );
		$this->assertSame( '', do_blocks( '<!-- wp:lpg/exchange-form /-->' ) );
		$this->assertFalse( wp_script_is( $handle, 'enqueued' ) );

		$this->set_settings( array() );
		CylinderBrand::seed();
		$this->assertStringContainsString( 'lpg-exchange', do_blocks( '<!-- wp:lpg/exchange-form /-->' ) );
		$this->assertTrue( wp_script_is( $handle, 'enqueued' ) );
		$this->assertContains( 'wp-i18n', wp_scripts()->registered[ $handle ]->deps );
	}

	// ----- Enquiry button --------------------------------------------------------------

	public function test_enquiry_button_empty_states(): void {
		$enquiry_only = $this->product( 'publish', 'QA-1' );

		$this->set_settings( array( 'whatsapp_number' => '' ) );
		$this->assertSame( '', EnquiryButton::render( array(), $enquiry_only ) );

		$this->set_settings( array() );
		$this->assertSame( '', EnquiryButton::render( array(), null ), 'No product.' );
		$this->assertSame( '', EnquiryButton::render( array(), $this->product( 'draft' ) ), 'Draft product for a visitor.' );
		$this->assertSame( '', EnquiryButton::render( array(), $this->priced_product() ), 'Purchasable product with the default showFor.' );
		$this->assertFalse( wp_script_is( 'lpg-enquiry-button-view-script', 'enqueued' ) );
	}

	public function test_enquiry_button_markup_nonce_and_no_js_link(): void {
		$product = $this->product( 'publish', 'QA-REG-1' );
		$html    = EnquiryButton::render( array(), $product );

		$expected_message = "Hello, I'm interested in [QA TEST] Regulator & Hose (QA-REG-1). " . get_permalink( $product );
		$expected_href    = MessageBuilder::link( '254700000001', $expected_message );

		$this->assertStringContainsString( 'class="lpg-enquiry"', $html );
		$this->assertSame( '254700000001', $this->attr( $html, 'data-lpg-number' ) );
		$this->assertSame( (string) $product, $this->attr( $html, 'data-lpg-product' ) );
		$this->assertSame( rest_url( 'lpg/v1/enquiry' ), $this->attr( $html, 'data-lpg-endpoint' ) );
		$this->assertNotFalse( wp_verify_nonce( $this->attr( $html, 'data-lpg-nonce' ), 'wp_rest' ) );
		$this->assertMatchesRegularExpression( '#<a class="lpg-enquiry__toggle wp-element-button" href="' . preg_quote( esc_url( $expected_href ), '#' ) . '"#', $html );
		$this->assertStringContainsString( 'class="lpg-enquiry__panel" id="', $html );
		$this->assertMatchesRegularExpression( '#<div class="lpg-enquiry__panel" id="[^"]+" hidden>#', $html );
		$this->assertMatchesRegularExpression( '#<label class="lpg-enquiry__label" for="([^"]+)">.*<textarea class="lpg-enquiry__message" id="\1"#s', $html );
		$this->assertStringContainsString( esc_textarea( $expected_message ) . '</textarea>', $html );
		$this->assertStringContainsString( 'lpg-notice__draft', $html );
		$this->assertStringContainsString( 'You can edit this message before sending.', $html );
		$this->assertStringContainsString( 'class="lpg-enquiry__open wp-element-button"', $html );
		$this->assertMatchesRegularExpression( '#target="_blank" rel="noopener noreferrer">Open WhatsApp<span class="screen-reader-text"> \(opens in a new tab\)</span></a>#', $html, 'DEF-013.' );
		$this->assertDoesNotMatchRegularExpression( '#class="lpg-enquiry__toggle[^>]*target=#', $html, 'The no-JS toggle opens in the same tab.' );
		$this->assertTrue( wp_script_is( 'lpg-enquiry-button-view-script', 'enqueued' ) );

		$this->assertNotSame( '', EnquiryButton::render( array( 'showFor' => 'all' ), $this->priced_product() ) );
		$this->assertSame( '', EnquiryButton::render( array( 'showFor' => 'bogus' ), $this->priced_product() ), 'Unknown showFor falls back to enquiry-only.' );
	}

	public function test_enquiry_button_escapes_hostile_template_and_product(): void {
		$this->set_settings( array( 'wa_template_product' => '</textarea><script>alert(1)</script>"><img src=x onerror=1> {product}' ) );
		$product = new WC_Product_Simple();
		$product->set_name( '"><svg onload=alert(2)>' );
		$product->set_status( 'publish' );
		$id   = $product->save();
		$html = EnquiryButton::render( array(), $id );

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( '<svg', $html );
		$this->assertSame( 1, substr_count( $html, '</textarea>' ) );
	}

	public function test_privacy_link_only_when_policy_page_is_published(): void {
		$html = EnquiryButton::render( array(), $this->product() );
		$this->assertStringContainsString( 'See our Privacy Policy.', $html );

		$page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
		update_option( 'wp_page_for_privacy_policy', $page );
		$this->assertStringNotContainsString( '<a href="' . get_permalink( $page ), RfqForm::render() );

		wp_publish_post( $page );
		$this->assertStringContainsString( '<a href="' . esc_url( get_permalink( $page ) ) . '">Privacy Policy</a>', RfqForm::render() );
	}

	// ----- RFQ form --------------------------------------------------------------------

	public function test_rfq_form_markup(): void {
		Segment::seed_terms();
		$html = RfqForm::render();

		$this->assertStringContainsString( 'class="lpg-rfq"', $html );
		$this->assertSame( rest_url( 'lpg/v1/rfq' ), $this->attr( $html, 'data-lpg-endpoint' ) );
		$this->assertNotFalse( wp_verify_nonce( $this->attr( $html, 'data-lpg-nonce' ), 'wp_rest' ) );
		$this->assertStringContainsString( '<form class="lpg-rfq__form" novalidate hidden>', $html );

		foreach ( array( 'contact.name', 'contact.organisation', 'contact.phone', 'contact.email', 'segment', 'items', 'items.0.description', 'items.0.qty', 'requirements', 'project', 'location', 'comments', 'consent' ) as $key ) {
			$this->assertStringContainsString( 'data-lpg-field="' . $key . '"', $html, $key );
		}
		// Every visible control has a label.
		preg_match_all( '/<(?:input|select|textarea)\b[^>]*\bid="([^"]+)"/', $html, $ids );
		foreach ( $ids[1] as $id ) {
			$this->assertStringContainsString( 'for="' . $id . '"', $html, "Label for $id" );
		}
		$this->assertMatchesRegularExpression( '/<input type="checkbox"[^>]*name="consent"[^>]*required/', $html );
		$this->assertDoesNotMatchRegularExpression( '/name="consent"[^>]*checked/', $html, 'Consent is never pre-ticked.' );
		$this->assertStringContainsString( 'I agree that', $html );
		$this->assertStringContainsString( 'lpg-notice__draft', $html );
		$this->assertMatchesRegularExpression( '/<div class="lpg-rfq__hp" aria-hidden="true" style="position:absolute;left:-10000px[^"]*"><label[^>]*>[^<]+<\/label><input type="text"[^>]*name="hp"[^>]*tabindex="-1"/', $html );
		$this->assertStringContainsString( '<option value="commercial">', $html );
		$this->assertStringContainsString( '<template class="lpg-rfq__item-template">', $html );
		$this->assertStringContainsString( 'data-lpg-field="items.__INDEX__.qty"', $html );
		$this->assertStringContainsString( 'role="status" aria-live="polite"', $html );
		$this->assertTrue( wp_script_is( 'lpg-rfq-form-view-script', 'enqueued' ) );
	}

	public function test_rfq_no_js_fallback_shows_phone_and_public_email_only(): void {
		$html = RfqForm::render();
		$this->assertStringContainsString( '<a class="lpg-phone-link" href="tel:+254700000001">+254 700 000 001</a>', $html );
		$this->assertStringNotContainsString( 'mailto:', $html, 'No public email set: phone only.' );
		$this->assertStringNotContainsString( 'sales@example.test', $html, 'Notification address never printed.' );
		$this->assertStringNotContainsString( 'owner@example.test', $html );

		$this->set_settings( array( 'public_email' => 'hello@example.test' ) );
		$html = RfqForm::render();
		$this->assertStringContainsString( '<a class="lpg-email-link" href="mailto:hello%40example.test">hello@example.test</a>', $html );
		$this->assertStringNotContainsString( 'sales@example.test', $html );

		$this->set_settings(
			array(
				'whatsapp_number' => '',
				'contact_phone'   => '',
				'public_email'    => 'not valid',
			)
		);
		$html = RfqForm::render();
		$this->assertStringContainsString( 'Please enable JavaScript', $html );
		$this->assertStringNotContainsString( 'mailto:', $html );
	}

	// ----- Exchange form ---------------------------------------------------------------

	public function test_exchange_form_empty_states(): void {
		$this->assertSame( '', ExchangeForm::render(), 'No brands.' );
		CylinderBrand::seed();
		$this->set_settings( array( 'whatsapp_number' => '' ) );
		$this->assertSame( '', ExchangeForm::render(), 'No number.' );
	}

	public function test_exchange_form_markup_and_brand_data(): void {
		CylinderBrand::seed();
		$term = wp_insert_term( '"><b>Evil & Gas</b>', CylinderBrand::TAXONOMY, array( 'slug' => 'evil-gas' ) );
		CylinderBrand::save_sizes( $term['term_id'], array( '"><script>x</script>', '9kg' ) );
		$this->set_settings( array( 'wa_template_exchange' => 'Hi {brand}"><script>alert(1)</script>' ) );

		$html = ExchangeForm::render();

		$this->assertStringContainsString( 'class="lpg-exchange"', $html );
		$this->assertSame( '254700000001', $this->attr( $html, 'data-lpg-number' ) );
		$this->assertSame( 'Hi {brand}"><script>alert(1)</script>', $this->attr( $html, 'data-lpg-template' ) );
		$this->assertSame(
			array(
				'exchange' => 'exchange',
				'new'      => 'purchase (new cylinder)',
			),
			json_decode( $this->attr( $html, 'data-lpg-types' ), true )
		);
		$this->assertNotFalse( wp_verify_nonce( $this->attr( $html, 'data-lpg-nonce' ), 'wp_rest' ) );
		$this->assertSame( rest_url( 'lpg/v1/enquiry' ), $this->attr( $html, 'data-lpg-endpoint' ) );

		preg_match( '/<option value="k-gas" data-lpg-name="([^"]*)" data-lpg-sizes="([^"]*)">K-Gas<\/option>/', $html, $m );
		$this->assertSame( 'K-Gas', html_entity_decode( $m[1] ) );
		$this->assertSame( array( '6kg', '13kg' ), json_decode( html_entity_decode( $m[2], ENT_QUOTES ), true ) );

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<b>Evil', $html );
		$this->assertStringContainsString( '<select id="', $html );
		$this->assertMatchesRegularExpression( '/name="size"[^>]*disabled/', $html );
		$this->assertMatchesRegularExpression( '/<input type="radio" name="exchange_type" value="exchange" checked>/', $html );
		$this->assertStringContainsString( 'By continuing to WhatsApp', $html );
		$this->assertStringContainsString( '<a class="lpg-whatsapp-link" href="https://wa.me/254700000001">', $html, 'No-JS fallback link.' );
		$this->assertStringContainsString( '<form class="lpg-exchange__form" novalidate hidden>', $html );
	}

	// ----- WhatsApp link ---------------------------------------------------------------

	public function test_whatsapp_link(): void {
		$html = WhatsAppLink::render( array() );
		$this->assertStringStartsWith( '<div class="lpg-contact-links">', $html );
		$this->assertStringContainsString( '<a class="lpg-whatsapp-link" href="https://wa.me/254700000001">', $html );
		$this->assertStringContainsString( 'WhatsApp us', $html );
		$this->assertStringContainsString( '<svg class="lpg-icon"', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertStringContainsString( '<a class="lpg-phone-link" href="tel:+254700000001">+254 700 000 001</a>', $html );
		$this->assertStringNotContainsString( 'position', $html, 'Not a floating bubble.' );

		$this->assertStringNotContainsString( 'lpg-phone-link', WhatsAppLink::render( array( 'showPhone' => false ) ) );

		$this->set_settings( array( 'contact_phone' => '254700000002' ) );
		$this->assertStringContainsString( 'tel:+254700000002', WhatsAppLink::render( array() ) );

		$this->set_settings( array( 'whatsapp_number' => '' ) );
		$this->assertSame( '', WhatsAppLink::render( array() ) );
		$this->set_settings( array( 'whatsapp_number' => 'javascript:alert(1)' ) );
		$this->assertSame( '', WhatsAppLink::render( array() ), 'Invalid stored number renders nothing.' );
	}

	// ----- Business info ---------------------------------------------------------------

	public function test_business_info_placeholders_and_line_breaks(): void {
		$html = BusinessInfo::render();
		$this->assertStringStartsWith( '<address class="lpg-business-info">', $html );
		$this->assertStringContainsString( '[Shop address, Nairobi]', $html );
		$this->assertStringContainsString( '[Opening hours]', $html );
		$this->assertStringContainsString( 'href="tel:+254700000001"', $html );

		$this->set_settings(
			array(
				'shop_address'  => "1 Test Road\n<script>x</script>Nairobi",
				'opening_hours' => "Mon–Fri 8–5\nSat 9–1",
			)
		);
		$html = BusinessInfo::render();
		$this->assertStringContainsString( "1 Test Road<br>\n&lt;script&gt;x&lt;/script&gt;Nairobi", $html );
		$this->assertStringContainsString( "Mon–Fri 8–5<br>\nSat 9–1", $html );
		$this->assertStringNotContainsString( '<script', $html );

		$this->set_settings(
			array(
				'shop_address'    => '',
				'opening_hours'   => '',
				'whatsapp_number' => '',
				'contact_phone'   => '',
			)
		);
		$this->assertSame( '', BusinessInfo::render() );
	}

	public function test_blocks_render_through_do_blocks(): void {
		$this->assertStringContainsString( 'lpg-business-info', do_blocks( '<!-- wp:lpg/business-info /-->' ) );
		$this->assertStringContainsString( 'lpg-contact-links', do_blocks( '<!-- wp:lpg/whatsapp-link {"showPhone":false} /-->' ) );
		$this->assertStringContainsString( 'lpg-rfq', do_blocks( '<!-- wp:lpg/rfq-form /-->' ) );
	}
}
