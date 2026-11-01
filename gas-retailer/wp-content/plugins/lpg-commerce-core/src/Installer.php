<?php
/**
 * Activation, upgrade and deactivation routines.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce;

defined( 'ABSPATH' ) || exit;

/**
 * Installs and upgrades plugin state. Idempotent: safe to run more than once.
 */
final class Installer {

	/**
	 * Option holding the installed plugin version (not autoloaded).
	 */
	public const VERSION_OPTION = 'lpg_commerce_version';

	/**
	 * Option holding plugin settings (not autoloaded).
	 */
	public const SETTINGS_OPTION = 'lpg_settings';

	/**
	 * Activation hook.
	 */
	public static function activate(): void {
		self::install();
	}

	/**
	 * Deactivation hook. Keeps all business data.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Create or upgrade plugin state.
	 */
	public static function install(): void {
		if ( false === get_option( self::SETTINGS_OPTION ) ) {
			add_option( self::SETTINGS_OPTION, self::default_settings(), '', false );
		}

		update_option( self::VERSION_OPTION, LPG_COMMERCE_VERSION, false );
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'whatsapp_number'          => '',
			'delete_data_on_uninstall' => false,
		);
	}
}
