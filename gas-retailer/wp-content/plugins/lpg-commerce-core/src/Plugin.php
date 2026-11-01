<?php
/**
 * Plugin bootstrap and service registry.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the plugin once WooCommerce is available and registers every service.
 *
 * Owned by the Lead Architect: adding a service here is a shared-interface change.
 */
final class Plugin {

	/**
	 * Service classes registered on boot, in order. Each must implement Service.
	 *
	 * @var array<int, class-string<Service>>
	 */
	private const SERVICES = array(
		Taxonomies\Segment::class,
		Specs\ProfilePostType::class,
		Specs\ProductSpecsPanel::class,
		Specs\AttributeSync::class,
		Relations\Compatibility::class,
		Relations\Documents::class,
		Commerce\EnquiryOnly::class,
		Commerce\EnquiryDisplay::class,
		Commerce\ProductFields::class,
		Seo\StructuredData::class,
		Settings\SettingsTab::class,
		Taxonomies\CylinderBrand::class,
		Enquiries\RfqPostType::class,
		Enquiries\EnquiryPostType::class,
		Enquiries\AdminScreens::class,
		Enquiries\Notifications::class,
		Rest\RfqController::class,
		Rest\EnquiryController::class,
		Privacy\Exporters::class,
		Privacy\Retention::class,
		Security\RestHardening::class,
		Blocks\Registry::class,
	);

	/**
	 * Whether boot() has already run.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Entry point, hooked to `plugins_loaded`.
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		if ( ! self::woocommerce_active() ) {
			add_action( 'admin_notices', array( self::class, 'missing_woocommerce_notice' ) );
			return;
		}

		self::maybe_upgrade();

		foreach ( self::services() as $service_class ) {
			( new $service_class() )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( Cli\SeedCommand::class ) ) {
			\WP_CLI::add_command( 'lpg seed', Cli\SeedCommand::class );
		}

		/**
		 * Fires after all LPG Commerce Core services are registered.
		 */
		do_action( 'lpg_loaded' );
	}

	/**
	 * Service classes to register. Filterable for tests and extensions.
	 *
	 * @return array<int, class-string<Service>>
	 */
	public static function services(): array {
		/**
		 * Filters the list of LPG Commerce Core service classes.
		 *
		 * @param array<int, class-string<Service>> $services Service class names.
		 */
		$services = (array) apply_filters( 'lpg_services', self::SERVICES );

		return array_values(
			array_filter(
				$services,
				static fn( $class_name ) => is_string( $class_name ) && is_subclass_of( $class_name, Service::class )
			)
		);
	}

	/**
	 * Whether WooCommerce is loaded.
	 */
	public static function woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Admin notice shown when WooCommerce is missing. The plugin stays inactive.
	 */
	public static function missing_woocommerce_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'LPG Commerce Core needs WooCommerce to be installed and active. Its features are switched off until then.', 'lpg-commerce-core' )
		);
	}

	/**
	 * Runs the installer when the stored version differs from the code version.
	 */
	private static function maybe_upgrade(): void {
		if ( get_option( Installer::VERSION_OPTION ) !== LPG_COMMERCE_VERSION ) {
			Installer::install();
		}
	}
}
