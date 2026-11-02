<?php
/**
 * Small counters for rate limiting.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Windowed counters (C22 M1). Keys include their time window, so they expire
 * on their own and never need resetting.
 *
 * With a persistent object cache (`wp_using_ext_object_cache()`), increments
 * use `wp_cache_add()` + `wp_cache_incr()`, which are atomic in Redis and
 * Memcached. Without one (typical shared hosting) they fall back to
 * transients: read, add one, write. Two simultaneous requests can then both
 * read the same value and one increment is lost. That residual race only
 * lets a burst through a little over the limit; it never blocks a genuine
 * visitor early, which is the acceptable side to err on.
 */
final class RateCounter {

	private const GROUP = 'lpg_rate';

	/**
	 * Current value of a counter.
	 *
	 * @param string $key Key (short, already hashed).
	 */
	public static function get( string $key ): int {
		if ( wp_using_ext_object_cache() ) {
			return (int) wp_cache_get( $key, self::GROUP );
		}
		return (int) get_transient( $key );
	}

	/**
	 * Increment and return the new value.
	 *
	 * @param string $key Key.
	 * @param int    $ttl Lifetime in seconds.
	 */
	public static function incr( string $key, int $ttl ): int {
		$ttl = max( 1, $ttl );
		if ( wp_using_ext_object_cache() ) {
			wp_cache_add( $key, 0, self::GROUP, $ttl );
			$value = wp_cache_incr( $key, 1, self::GROUP );
			return false === $value ? 1 : (int) $value;
		}
		$value = (int) get_transient( $key ) + 1;
		set_transient( $key, $value, $ttl );
		return $value;
	}

	/**
	 * Set a flag only if it is not set yet. Returns true for the first caller.
	 *
	 * @param string $key Key.
	 * @param int    $ttl Lifetime in seconds.
	 */
	public static function add_once( string $key, int $ttl ): bool {
		$ttl = max( 1, $ttl );
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_add( $key, 1, self::GROUP, $ttl );
		}
		if ( false !== get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, 1, $ttl );
		return true;
	}

	/**
	 * Transient-safe key: prefix plus a salted hash of the parts.
	 *
	 * @param string $prefix Prefix (e.g. lpg_rl_).
	 * @param string ...$parts Parts.
	 */
	public static function key( string $prefix, string ...$parts ): string {
		return $prefix . substr( hash_hmac( 'sha256', implode( '|', $parts ), wp_salt( 'nonce' ) ), 0, 32 );
	}
}
