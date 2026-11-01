<?php
/**
 * Contract for plugin services.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce;

defined( 'ABSPATH' ) || exit;

/**
 * A unit of functionality that attaches its own hooks.
 */
interface Service {

	/**
	 * Attach WordPress/WooCommerce hooks. Must not do expensive work.
	 */
	public function register(): void;
}
