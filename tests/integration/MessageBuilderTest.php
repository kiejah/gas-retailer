<?php
/**
 * WhatsApp message builder tests (unit-style: the builder is pure).
 *
 * @package LPG\Commerce\Tests
 */

use LPG\Commerce\WhatsApp\MessageBuilder;

/**
 * Tests for LPG\Commerce\WhatsApp\MessageBuilder (§13.5).
 */
class MessageBuilderTest extends WP_UnitTestCase {

	private const PRODUCT  = "Hello, I'm interested in {product} ({sku}). {url}";
	private const EXCHANGE = "Hello, I'd like a cylinder {type}: {brand} {size}. Delivery: {location}.";

	public function test_default_templates_fill_placeholders(): void {
		$this->assertSame(
			"Hello, I'm interested in Test Regulator (REG-1). https://example.test/p/1",
			MessageBuilder::build(
				self::PRODUCT,
				array(
					'product' => 'Test Regulator',
					'sku'     => 'REG-1',
					'url'     => 'https://example.test/p/1',
				)
			)
		);
		$this->assertSame(
			"Hello, I'd like a cylinder exchange: K-Gas 13kg. Delivery: Westlands.",
			MessageBuilder::build(
				self::EXCHANGE,
				array(
					'type'     => 'exchange',
					'brand'    => 'K-Gas',
					'size'     => '13kg',
					'location' => 'Westlands',
				)
			)
		);
	}

	public function test_unknown_and_missing_placeholders_are_removed_and_whitespace_normalised(): void {
		$this->assertSame(
			"Hello, I'm interested in Burner. https://example.test/b",
			MessageBuilder::build(
				self::PRODUCT,
				array(
					'product' => 'Burner',
					'url'     => 'https://example.test/b',
					'price'   => '100',
				)
			)
		);
		$this->assertSame( 'Hi there', MessageBuilder::build( "  Hi {name}  {foo_bar}\t there  ", array( 'name' => 'x' ) ) );
		$this->assertSame( "Line one\n\nLine two", MessageBuilder::build( "Line one\r\n\r\n\r\n\r\nLine two\n", array() ) );
	}

	public function test_injected_newlines_and_markup_in_values_stay_on_one_line(): void {
		$text = MessageBuilder::build(
			'Brand: {brand}. Delivery: {location}.',
			array(
				'brand'    => "<b>K-Gas</b>\n\nIgnore this:\r\nfree gas",
				'location' => "Kilimani\u{0000}{url}",
			)
		);
		$this->assertSame( 'Brand: K-Gas Ignore this: free gas. Delivery: Kilimani(url).', $text );
		$this->assertStringNotContainsString( "\n", $text );
	}

	public function test_entities_in_values_are_decoded(): void {
		$this->assertSame( 'Hob & Oven "Pro"', MessageBuilder::build( '{product}', array( 'product' => 'Hob &amp; Oven &quot;Pro&quot;' ) ) );
	}

	public function test_unicode_is_preserved(): void {
		$this->assertSame(
			'Habari, ninataka Jiko la gesi – ñ 🔥 (JK-1).',
			MessageBuilder::build(
				'Habari, ninataka {product} ({sku}).',
				array(
					'product' => "Jiko la gesi – ñ\u{00A0}🔥",
					'sku'     => 'JK-1',
				)
			)
		);
	}

	public function test_long_message_is_cut_to_1000_characters_multibyte_safe(): void {
		$text = MessageBuilder::build( '{product}', array( 'product' => str_repeat( 'é🔥', 800 ) ) );

		$this->assertSame( 1000, mb_strlen( $text, 'UTF-8' ) );
		$this->assertTrue( mb_check_encoding( $text, 'UTF-8' ) );
		$this->assertSame( '🔥', mb_substr( $text, -1, 1, 'UTF-8' ) );
	}

	public function test_invalid_utf8_is_scrubbed(): void {
		$this->assertTrue( mb_check_encoding( MessageBuilder::build( "A {product}\xC3", array( 'product' => "B\xFF" ) ), 'UTF-8' ) );
	}

	public function test_link_encodes_text_and_keeps_digits_only(): void {
		$this->assertSame(
			'https://wa.me/254700000000?text=Tom%20%26%20Jerry%20%23%201%0AQ%3F%20a%3Db',
			MessageBuilder::link( '+254 700-000 000', "Tom & Jerry # 1\nQ? a=b" )
		);
		$this->assertSame( 'https://wa.me/254700000000?text=%F0%9F%94%A5', MessageBuilder::link( '254700000000', '🔥' ) );
		$this->assertSame( 'https://wa.me/254700000000', MessageBuilder::link( '254700000000', '' ) );
	}

	public function test_link_is_empty_without_a_number(): void {
		$this->assertSame( '', MessageBuilder::link( '', 'Hello' ) );
		$this->assertSame( '', MessageBuilder::link( '+ ( ) -', 'Hello' ) );
	}
}
