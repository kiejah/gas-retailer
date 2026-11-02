<?php
/**
 * Client IP resolution for rate limiting.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Works out which client a request is "from" for rate limiting (C22 M1).
 *
 * By default only REMOTE_ADDR is used: proxy headers are trivially spoofed.
 * Behind a reverse proxy or CDN, define the proxies in wp-config.php:
 *
 *     define( 'LPG_TRUSTED_PROXIES', array( '203.0.113.10', '198.51.100.0/24', '2001:db8::/32' ) );
 *
 * When REMOTE_ADDR is one of them, the client IP is taken from
 * `CF-Connecting-IP` if present and valid, otherwise from the right-most
 * `X-Forwarded-For` entry that is not itself a trusted proxy. Headers from
 * untrusted peers are ignored.
 *
 * IPv6 clients are bucketed by their /64 prefix (one subscriber usually
 * controls a whole /64); IPv4 clients by address.
 */
final class ClientIp {

	/**
	 * The client IP for this request, honouring LPG_TRUSTED_PROXIES.
	 */
	public static function current(): string {
		$trusted = defined( 'LPG_TRUSTED_PROXIES' ) && is_array( LPG_TRUSTED_PROXIES ) ? LPG_TRUSTED_PROXIES : array();
		return self::resolve( $_SERVER, $trusted ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is validated as an IP in resolve().
	}

	/**
	 * Resolve the client IP from server variables.
	 *
	 * @param array<string, mixed> $server  $_SERVER-like array.
	 * @param array<int, string>   $trusted Trusted proxy IPs / CIDRs.
	 * @return string IP, or '' when REMOTE_ADDR is missing or invalid.
	 */
	public static function resolve( array $server, array $trusted ): string {
		$remote = self::valid( $server['REMOTE_ADDR'] ?? '' );
		if ( '' === $remote || ! $trusted || ! self::in_ranges( $remote, $trusted ) ) {
			return $remote;
		}

		$cf = self::valid( $server['HTTP_CF_CONNECTING_IP'] ?? '' );
		if ( '' !== $cf ) {
			return $cf;
		}

		$forwarded = is_string( $server['HTTP_X_FORWARDED_FOR'] ?? null ) ? explode( ',', $server['HTTP_X_FORWARDED_FOR'] ) : array();
		foreach ( array_reverse( $forwarded ) as $entry ) {
			$ip = self::valid( $entry );
			if ( '' === $ip ) {
				// A malformed hop: stop rather than trust anything to its left.
				break;
			}
			if ( ! self::in_ranges( $ip, $trusted ) ) {
				return $ip;
			}
		}
		return $remote;
	}

	/**
	 * Rate-limit bucket for an IP: IPv4 as is, IPv6 as its /64 prefix
	 * (IPv4-mapped IPv6 addresses count as the IPv4 address).
	 *
	 * @param string $ip IP.
	 */
	public static function bucket( string $ip ): string {
		$packed = '' !== $ip ? inet_pton( $ip ) : false;
		if ( false === $packed ) {
			return 'unknown';
		}
		if ( 16 === strlen( $packed ) ) {
			if ( str_repeat( "\0", 10 ) . "\xff\xff" === substr( $packed, 0, 12 ) ) {
				return (string) inet_ntop( substr( $packed, 12 ) );
			}
			return bin2hex( substr( $packed, 0, 8 ) ) . '::/64';
		}
		return (string) inet_ntop( $packed );
	}

	/**
	 * Whether an IP is in any of the given IPs / CIDR ranges (IPv4 or IPv6).
	 *
	 * @param string             $ip     IP.
	 * @param array<int, string> $ranges IPs or CIDRs.
	 */
	public static function in_ranges( string $ip, array $ranges ): bool {
		$packed = inet_pton( $ip );
		if ( false === $packed ) {
			return false;
		}
		foreach ( $ranges as $range ) {
			if ( ! is_string( $range ) || '' === $range ) {
				continue;
			}
			$parts = explode( '/', trim( $range ), 2 );
			$net   = inet_pton( $parts[0] );
			if ( false === $net || strlen( $net ) !== strlen( $packed ) ) {
				continue;
			}
			$max  = strlen( $net ) * 8;
			$bits = isset( $parts[1] ) && ctype_digit( $parts[1] ) ? min( $max, (int) $parts[1] ) : $max;

			$bytes = intdiv( $bits, 8 );
			if ( substr( $packed, 0, $bytes ) !== substr( $net, 0, $bytes ) ) {
				continue;
			}
			$rest = $bits % 8;
			if ( 0 === $rest ) {
				return true;
			}
			$mask = ( 0xff << ( 8 - $rest ) ) & 0xff;
			if ( ( ord( $packed[ $bytes ] ) & $mask ) === ( ord( $net[ $bytes ] ) & $mask ) ) {
				return true;
			}
		}//end foreach
		return false;
	}

	/**
	 * A trimmed, valid IP string, or ''.
	 *
	 * @param mixed $value Value.
	 */
	private static function valid( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		return false !== filter_var( $value, FILTER_VALIDATE_IP ) ? $value : '';
	}
}
