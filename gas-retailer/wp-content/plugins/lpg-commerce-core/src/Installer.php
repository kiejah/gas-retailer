<?php
/**
 * Activation, upgrade and deactivation routines.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce;

use LPG\Commerce\Specs\ProfilePostType;
use LPG\Commerce\Taxonomies\Segment;

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
		ProfilePostType::add_caps();
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
		// Caps are granted on activation or first install only, so an upgrade
		// never re-grants caps an operator removed (L4).
		if ( false === get_option( self::VERSION_OPTION ) ) {
			ProfilePostType::add_caps();
		}
		Segment::seed_terms();

		// Rebuild rewrite rules on the next request (segment archives).
		delete_option( 'rewrite_rules' );

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
