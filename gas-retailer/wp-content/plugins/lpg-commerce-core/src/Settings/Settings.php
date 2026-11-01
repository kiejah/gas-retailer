<?php
/**
 * Read access to the `lpg_settings` option.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Settings;

use LPG\Commerce\Installer;

defined( 'ABSPATH' ) || exit;

/**
 * Static getter over `lpg_settings` with the §13.1 defaults merged in.
 *
 * Not a service: it is usable from anywhere, at any time. The option is read
 * once per request and the cache is dropped whenever the option is added,
 * updated or deleted.
 */
final class Settings {

	/**
	 * Keys holding digits-only international phone numbers.
	 */
	public const PHONE_KEYS = array( 'whatsapp_number', 'contact_phone' );

	/**
	 * Validation pattern for phone keys (§13.1).
	 */
	public const PHONE_PATTERN = '/^[1-9]\d{7,14}$/';

	/**
	 * Cached merged settings, or null when not loaded yet.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Get one setting, with defaults applied. Unknown keys that are not stored
	 * return null.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( string $key ): mixed {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/**
	 * All settings, with defaults applied and known keys normalised to their
	 * documented types. Keys the plugin does not know are passed through.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		self::hook();
		if ( null === self::$cache ) {
			$stored      = get_option( Installer::SETTINGS_OPTION, array() );
			self::$cache = self::normalise( array_merge( self::defaults(), is_array( $stored ) ? $stored : array() ) );
		}
		return self::$cache;
	}

	/**
	 * Default values (§13.1).
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return Installer::default_settings();
	}

	/**
	 * Drop the per-request cache.
	 */
	public static function flush(): void {
		self::$cache = null;
	}

	/**
	 * Attach the cache invalidation hooks if they are not attached (checked
	 * each time rather than with a flag, so a hook reset cannot leave a
	 * stale cache).
	 */
	private static function hook(): void {
		foreach ( array( 'add_option_', 'update_option_', 'delete_option_' ) as $prefix ) {
			if ( false === has_action( $prefix . Installer::SETTINGS_OPTION, array( self::class, 'flush' ) ) ) {
				add_action( $prefix . Installer::SETTINGS_OPTION, array( self::class, 'flush' ) );
			}
		}
	}

	/**
	 * Cast known keys to their documented types.
	 *
	 * @param array<string, mixed> $settings Merged settings.
	 * @return array<string, mixed>
	 */
	private static function normalise( array $settings ): array {
		foreach ( self::defaults() as $key => $default ) {
			$value = $settings[ $key ];
			if ( in_array( $key, self::PHONE_KEYS, true ) ) {
				$settings[ $key ] = is_scalar( $value ) ? preg_replace( '/\D+/', '', (string) $value ) : '';
			} elseif ( is_bool( $default ) ) {
				$settings[ $key ] = (bool) $value;
			} elseif ( is_int( $default ) ) {
				$settings[ $key ] = is_numeric( $value ) ? max( 0, (int) $value ) : $default;
			} else {
				$settings[ $key ] = is_scalar( $value ) ? (string) $value : $default;
			}
		}
		return $settings;
	}
}
