<?php
/**
 * Plugin Name:          LPG Commerce Core
 * Description:          LPG-specific business functionality: segments, technical specifications, compatibility, enquiries, quotation requests and WhatsApp enquiries. Independent of the active theme.
 * Version:              0.1.0
 * Requires at least:    6.6
 * Requires PHP:         8.1
 * Requires Plugins:     woocommerce
 * Author:               Gas-Plug
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          lpg-commerce-core
 * Domain Path:          /languages
 * WC requires at least: 9.0
 * WC tested up to:      11.2
 *
 * @package LPG\Commerce
 */

defined( 'ABSPATH' ) || exit;

define( 'LPG_COMMERCE_VERSION', '0.1.0' );
define( 'LPG_COMMERCE_FILE', __FILE__ );
define( 'LPG_COMMERCE_PATH', plugin_dir_path( __FILE__ ) );
define( 'LPG_COMMERCE_URL', plugin_dir_url( __FILE__ ) );

/*
 * PSR-4 autoloader for LPG\Commerce\* → src/*. Built in, so the plugin
 * has no runtime Composer dependency (decision A5).
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'LPG\\Commerce\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) );
		$file     = LPG_COMMERCE_PATH . 'src/' . $relative . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

// Declare compatibility with WooCommerce HPOS and the cart/checkout blocks.
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', LPG_COMMERCE_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', LPG_COMMERCE_FILE, true );
		}
	}
);

register_activation_hook( __FILE__, array( \LPG\Commerce\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \LPG\Commerce\Installer::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \LPG\Commerce\Plugin::class, 'boot' ) );
