<?php
/**
 * WhatsApp message and link builder.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\WhatsApp;

defined( 'ABSPATH' ) || exit;

/**
 * Builds WhatsApp click-to-chat messages and links (§13.5).
 *
 * Pure: no WordPress calls, no state. Output is plain text / a URL; callers
 * escape it for their context (esc_url, esc_textarea, ...).
 */
final class MessageBuilder {

	/**
	 * Supported placeholders, without braces.
	 */
	public const PLACEHOLDERS = array( 'product', 'sku', 'url', 'brand', 'size', 'type', 'location' );

	/**
	 * Maximum message length in characters (not bytes).
	 */
	public const MAX_LENGTH = 1000;

	/**
	 * Fill a template.
	 *
	 * Supported placeholders are replaced by their value (plain text, HTML
	 * removed, entities decoded, all whitespace including newlines collapsed
	 * to one space so a value cannot add lines). Any other `{name}` is
	 * removed. Then whitespace is normalised (spaces collapsed per line, at
	 * most one blank line, ends trimmed) and the result is cut to MAX_LENGTH
	 * characters without splitting a multibyte character.
	 *
	 * @param string               $template Template text.
	 * @param array<string, mixed> $vars     Placeholder => value.
	 */
	public static function build( string $template, array $vars ): string {
		$replace = array();
		foreach ( self::PLACEHOLDERS as $name ) {
			$value                        = $vars[ $name ] ?? '';
			$replace[ '{' . $name . '}' ] = is_scalar( $value ) ? self::clean_value( (string) $value ) : '';
		}

		$text = strtr( self::scrub( $template ), $replace );
		$text = (string) preg_replace( '/\{[A-Za-z0-9_\-]*\}/', '', $text );

		return self::truncate( self::normalise( $text ) );
	}

	/**
	 * Click-to-chat link: `https://wa.me/{digits}?text={rawurlencoded}`.
	 *
	 * @param string $number Phone number; everything but digits is dropped.
	 * @param string $text   Message (already built).
	 * @return string The URL, or '' when there are no digits.
	 */
	public static function link( string $number, string $text ): string {
		$digits = (string) preg_replace( '/\D+/', '', $number );
		if ( '' === $digits ) {
			return '';
		}
		$url = 'https://wa.me/' . $digits;
		if ( '' !== $text ) {
			$url .= '?text=' . rawurlencode( $text );
		}
		return $url;
	}

	/**
	 * Placeholder value to one line of plain text.
	 *
	 * @param string $value Raw value.
	 */
	private static function clean_value( string $value ): string {
		$value = html_entity_decode( strip_tags( self::scrub( $value ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Pure class; plain text only.
		// Braces in a value must not form new placeholders.
		$value = str_replace( array( '{', '}' ), array( '(', ')' ), $value );
		return trim( (string) preg_replace( '/[\s\p{Z}]+/u', ' ', $value ) );
	}

	/**
	 * Make a string valid UTF-8 and drop control characters except newlines and tabs.
	 *
	 * @param string $text Text.
	 */
	private static function scrub( string $text ): string {
		if ( function_exists( 'mb_scrub' ) ) {
			$text = mb_scrub( $text, 'UTF-8' );
		} elseif ( ! preg_match( '//u', $text ) ) {
			$text = (string) iconv( 'UTF-8', 'UTF-8//IGNORE', $text );
		}
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		return (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text );
	}

	/**
	 * Collapse spaces within lines, keep at most one blank line, trim.
	 *
	 * @param string $text Text.
	 */
	private static function normalise( string $text ): string {
		$lines = explode( "\n", $text );
		$lines = array_map( static fn( string $line ): string => trim( (string) preg_replace( '/[ \t\p{Zs}]+/u', ' ', $line ) ), $lines );
		$text  = implode( "\n", $lines );
		$text  = (string) preg_replace( '/\n{3,}/', "\n\n", $text );
		// Tidy punctuation left behind by removed placeholders: " ." and "()".
		$text = (string) preg_replace( '/ ?\(\s*\)/u', '', $text );
		$text = (string) preg_replace( '/ +([.,:;!?])/u', '$1', $text );
		return trim( $text );
	}

	/**
	 * Cut to MAX_LENGTH characters (multibyte safe).
	 *
	 * @param string $text Text.
	 */
	private static function truncate( string $text ): string {
		if ( mb_strlen( $text, 'UTF-8' ) <= self::MAX_LENGTH ) {
			return $text;
		}
		return rtrim( mb_substr( $text, 0, self::MAX_LENGTH, 'UTF-8' ) );
	}
}
