<?php
/**
 * Activation, upgrade and deactivation routines.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce;

use LPG\Commerce\Enquiries\EnquiryPostType;
use LPG\Commerce\Enquiries\RfqPostType;
use LPG\Commerce\Privacy\Retention;
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
		self::add_caps();
		self::install();
	}

	/**
	 * Grant every plugin capability to administrator and shop_manager.
	 * Called on activation and first install only (L4).
	 */
	public static function add_caps(): void {
		ProfilePostType::add_caps();
		RfqPostType::add_caps();
		EnquiryPostType::add_caps();
	}

	/**
	 * Deactivation hook. Keeps all business data.
	 */
	public static function deactivate(): void {
		Retention::unschedule();
		flush_rewrite_rules();
	}

	/**
	 * Create or upgrade plugin state.
	 */
	public static function install(): void {
		// Caps are granted on activation or first install only, so an upgrade
		// never re-grants caps an operator removed (L4).
		if ( false === get_option( self::VERSION_OPTION ) ) {
			self::add_caps();
		}
		// Idempotent; also covers upgrades made without re-activation. Deferred
		// to `init` when needed: scheduling reads `cron_schedules`, which other
		// plugins translate, and install() can run on `plugins_loaded`.
		if ( did_action( 'init' ) ) {
			Retention::schedule();
		} else {
			add_action( 'init', array( Retention::class, 'schedule' ) );
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
	 * Default settings (architecture §13.1). Existing installs keep their
	 * stored values; Settings::get() merges these in for missing keys.
	 *
	 * `public_email` is the optional address shown on the site (C22 L3); the
	 * notification addresses are never printed on the front end.
	 * Contact numbers and emails default to empty: real values are entered in
	 * WooCommerce → Settings → LPG and never ship in code (P-023). Address and
	 * hours are marked placeholders (W-002). Retention is 0 (off) until the
	 * client approves a period (W-007). Strings are stored content, so
	 * they are not translated here (this can run before `init`).
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'whatsapp_number'          => '',
			'contact_phone'            => '',
			'notify_sales_email'       => '',
			'notify_owner_email'       => '',
			'public_email'             => '',
			'shop_address'             => '[Shop address, Nairobi]',
			'opening_hours'            => '[Opening hours]',
			'wa_template_product'      => "Hello, I'm interested in {product} ({sku}). {url}",
			'wa_template_exchange'     => "Hello, I'd like a cylinder {type}: {brand} {size}. Delivery: {location}.",
			'privacy_policy_version'   => 'draft-2026-11-01',
			'enquiry_retention_months' => 0,
			'delete_data_on_uninstall' => false,
		);
	}
}
