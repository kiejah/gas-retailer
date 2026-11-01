<?php
/**
 * WooCommerce → Settings → LPG tab.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Settings;

use LPG\Commerce\Installer;
use LPG\Commerce\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Edits `lpg_settings` through the WooCommerce Settings API (§13.1).
 *
 * WooCommerce verifies the `manage_woocommerce` capability and the
 * `woocommerce-settings` nonce before firing the update action; the save
 * handler checks both again so it is safe however it is reached. Only the
 * keys this form manages are written: anything else stored in the option is
 * kept as it is.
 */
final class SettingsTab implements Service {

	public const TAB = 'lpg';

	public const CAPABILITY = 'manage_woocommerce';

	/**
	 * Maximum length of a WhatsApp message template.
	 */
	private const TEMPLATE_MAX = 1000;

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_tab' ), 50 );
		add_action( 'woocommerce_settings_' . self::TAB, array( $this, 'render' ) );
		add_action( 'woocommerce_update_options_' . self::TAB, array( $this, 'handle_save' ) );
	}

	/**
	 * Add the tab.
	 *
	 * @param array<string, string> $tabs Tabs, slug => label.
	 * @return array<string, string>
	 */
	public function add_tab( $tabs ): array {
		$tabs              = is_array( $tabs ) ? $tabs : array();
		$tabs[ self::TAB ] = __( 'LPG', 'lpg-commerce-core' );
		return $tabs;
	}

	/**
	 * Render the tab's fields (WooCommerce prints the form, nonce and button).
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		\WC_Admin_Settings::output_fields( self::fields() );
	}

	/**
	 * Save handler for `woocommerce_update_options_lpg`.
	 *
	 * @return string[] Validation errors (also shown as admin errors).
	 */
	public function handle_save(): array {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return array();
		}
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'woocommerce-settings' ) ) {
			return array();
		}
		// Each value is sanitised field by field in sanitize().
		$input = isset( $_POST[ Installer::SETTINGS_OPTION ] ) && is_array( $_POST[ Installer::SETTINGS_OPTION ] ) ? wp_unslash( $_POST[ Installer::SETTINGS_OPTION ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$stored = get_option( Installer::SETTINGS_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$result = self::sanitize( $input, $stored );

		update_option( Installer::SETTINGS_OPTION, $result['settings'], false );

		if ( class_exists( '\WC_Admin_Settings' ) ) {
			foreach ( $result['errors'] as $error ) {
				\WC_Admin_Settings::add_error( $error );
			}
		}
		return $result['errors'];
	}

	/**
	 * Validate submitted values against the stored settings.
	 *
	 * Invalid values are rejected with an error and the stored value is kept.
	 * Keys the form does not manage are copied through untouched.
	 *
	 * @param array<string, mixed> $input  Submitted values (unslashed).
	 * @param array<string, mixed> $stored Currently stored option value.
	 * @return array{settings: array<string, mixed>, errors: string[]}
	 */
	public static function sanitize( array $input, array $stored ): array {
		$defaults = Settings::defaults();
		$settings = $stored;
		$errors   = array();

		$labels = array(
			'whatsapp_number'    => __( 'WhatsApp number', 'lpg-commerce-core' ),
			'contact_phone'      => __( 'Contact phone', 'lpg-commerce-core' ),
			'notify_sales_email' => __( 'Sales notification email', 'lpg-commerce-core' ),
			'notify_owner_email' => __( 'Owner notification email (Cc)', 'lpg-commerce-core' ),
		);

		foreach ( Settings::PHONE_KEYS as $key ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$raw = is_scalar( $input[ $key ] ) ? preg_replace( '/[\s+\-().]+/', '', (string) $input[ $key ] ) : null;
			if ( '' === $raw ) {
				$settings[ $key ] = '';
			} elseif ( is_string( $raw ) && preg_match( Settings::PHONE_PATTERN, $raw ) ) {
				$settings[ $key ] = $raw;
			} else {
				$errors[] = sprintf(
					/* translators: %s: field label. */
					__( '%s was not saved: enter digits only in international format without the leading 0, for example 2547XXXXXXXX.', 'lpg-commerce-core' ),
					$labels[ $key ]
				);
			}
		}

		foreach ( array( 'notify_sales_email', 'notify_owner_email' ) as $key ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$raw = is_scalar( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : null;
			if ( '' === $raw ) {
				$settings[ $key ] = '';
			} elseif ( is_string( $raw ) && is_email( $raw ) && sanitize_email( $raw ) === $raw ) {
				$settings[ $key ] = $raw;
			} else {
				$errors[] = sprintf(
					/* translators: %s: field label. */
					__( '%s was not saved: enter a valid email address.', 'lpg-commerce-core' ),
					$labels[ $key ]
				);
			}
		}

		foreach ( array( 'shop_address', 'opening_hours' ) as $key ) {
			if ( array_key_exists( $key, $input ) && is_scalar( $input[ $key ] ) ) {
				$settings[ $key ] = sanitize_textarea_field( (string) $input[ $key ] );
			}
		}

		foreach ( array( 'wa_template_product', 'wa_template_exchange' ) as $key ) {
			if ( array_key_exists( $key, $input ) && is_scalar( $input[ $key ] ) ) {
				$value            = mb_substr( sanitize_textarea_field( (string) $input[ $key ] ), 0, self::TEMPLATE_MAX );
				$settings[ $key ] = '' === $value ? $defaults[ $key ] : $value;
			}
		}

		if ( array_key_exists( 'privacy_policy_version', $input ) && is_scalar( $input['privacy_policy_version'] ) ) {
			$settings['privacy_policy_version'] = mb_substr( sanitize_text_field( (string) $input['privacy_policy_version'] ), 0, 50 );
		}

		if ( array_key_exists( 'enquiry_retention_months', $input ) ) {
			$raw = is_scalar( $input['enquiry_retention_months'] ) ? trim( (string) $input['enquiry_retention_months'] ) : '';
			if ( preg_match( '/^\d{1,4}$/', $raw ) ) {
				$settings['enquiry_retention_months'] = (int) $raw;
			} else {
				$errors[] = __( 'Enquiry retention was not saved: enter a whole number of months, 0 or more (0 switches automatic deletion off).', 'lpg-commerce-core' );
			}
		}

		// A checkbox is absent from the POST when unticked.
		$settings['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] ) && 'no' !== $input['delete_data_on_uninstall'];

		return array(
			'settings' => $settings,
			'errors'   => $errors,
		);
	}

	/**
	 * Field definitions for WC_Admin_Settings::output_fields(). Values come
	 * from Settings::all() so defaults and stored types display correctly.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function fields(): array {
		$values = Settings::all();
		$name   = static fn( string $key ): string => Installer::SETTINGS_OPTION . '[' . $key . ']';
		$field  = static function ( string $key, string $type, string $title, string $desc = '', array $extra = array() ) use ( $values, $name ): array {
			$value = $values[ $key ] ?? '';
			if ( 'checkbox' === $type ) {
				$value = $value ? 'yes' : 'no';
			}
			return array_merge(
				array(
					'id'         => 'lpg_settings_' . $key,
					'field_name' => $name( $key ),
					'type'       => $type,
					'title'      => $title,
					'desc'       => $desc,
					'desc_tip'   => false,
					'value'      => is_scalar( $value ) ? (string) $value : '',
				),
				$extra
			);
		};

		$placeholders = '{product}, {sku}, {url}, {brand}, {size}, {type}, {location}';

		return array(
			array(
				'id'    => 'lpg_settings_contact',
				'type'  => 'title',
				'title' => __( 'Contact and WhatsApp', 'lpg-commerce-core' ),
				'desc'  => __( 'Used by the WhatsApp links, the contact details on the site and the quote request notifications. Leave the WhatsApp number empty to hide WhatsApp links.', 'lpg-commerce-core' ),
			),
			$field( 'whatsapp_number', 'text', __( 'WhatsApp number', 'lpg-commerce-core' ), __( 'Digits only, international format without + or a leading 0, for example 2547XXXXXXXX.', 'lpg-commerce-core' ), array( 'custom_attributes' => array( 'inputmode' => 'numeric' ) ) ),
			$field( 'contact_phone', 'text', __( 'Contact phone', 'lpg-commerce-core' ), __( 'Same format. Used for the call link.', 'lpg-commerce-core' ), array( 'custom_attributes' => array( 'inputmode' => 'numeric' ) ) ),
			$field( 'notify_sales_email', 'email', __( 'Sales notification email', 'lpg-commerce-core' ), __( 'New quote requests and enquiries are sent here.', 'lpg-commerce-core' ) ),
			$field( 'notify_owner_email', 'email', __( 'Owner notification email (Cc)', 'lpg-commerce-core' ), __( 'Copied on new quote requests.', 'lpg-commerce-core' ) ),
			$field( 'shop_address', 'textarea', __( 'Shop address', 'lpg-commerce-core' ), __( 'Shown in the footer, contact page and pickup details.', 'lpg-commerce-core' ), array( 'css' => 'min-height:5em;' ) ),
			$field( 'opening_hours', 'textarea', __( 'Opening hours', 'lpg-commerce-core' ), '', array( 'css' => 'min-height:5em;' ) ),
			array(
				'id'   => 'lpg_settings_contact',
				'type' => 'sectionend',
			),
			array(
				'id'    => 'lpg_settings_whatsapp',
				'type'  => 'title',
				'title' => __( 'WhatsApp message templates', 'lpg-commerce-core' ),
				'desc'  => sprintf(
					/* translators: %s: list of placeholders. */
					__( 'Available placeholders: %s. Unknown placeholders are removed. Leave a template empty to restore the default.', 'lpg-commerce-core' ),
					$placeholders
				),
			),
			$field( 'wa_template_product', 'textarea', __( 'Product enquiry', 'lpg-commerce-core' ), '', array( 'css' => 'min-height:4em;' ) ),
			$field( 'wa_template_exchange', 'textarea', __( 'Cylinder refill / exchange', 'lpg-commerce-core' ), '', array( 'css' => 'min-height:4em;' ) ),
			array(
				'id'   => 'lpg_settings_whatsapp',
				'type' => 'sectionend',
			),
			array(
				'id'    => 'lpg_settings_privacy',
				'type'  => 'title',
				'title' => __( 'Privacy and data', 'lpg-commerce-core' ),
			),
			$field( 'privacy_policy_version', 'text', __( 'Privacy policy version', 'lpg-commerce-core' ), __( 'Saved with each quote request consent. Change it when the privacy policy changes.', 'lpg-commerce-core' ) ),
			$field(
				'enquiry_retention_months',
				'number',
				__( 'Keep enquiries for (months)', 'lpg-commerce-core' ),
				__( '0 switches automatic deletion off. The retention period is pending client approval.', 'lpg-commerce-core' ),
				array(
					'custom_attributes' => array(
						'min'  => '0',
						'step' => '1',
					),
				)
			),
			$field( 'delete_data_on_uninstall', 'checkbox', __( 'Delete data on uninstall', 'lpg-commerce-core' ), __( 'Remove LPG data (spec profiles, specifications, segments and records) when the plugin is deleted.', 'lpg-commerce-core' ) ),
			array(
				'id'   => 'lpg_settings_privacy',
				'type' => 'sectionend',
			),
		);
	}
}
