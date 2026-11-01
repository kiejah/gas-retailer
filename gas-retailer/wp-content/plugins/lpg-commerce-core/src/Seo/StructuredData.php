<?php
/**
 * Adds spec values to WooCommerce product structured data.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Seo;

use LPG\Commerce\Service;
use LPG\Commerce\Specs\ProductSpecs;

defined( 'ABSPATH' ) || exit;

/**
 * §11.8: one `additionalProperty` PropertyValue per spec; nothing else.
 */
final class StructuredData implements Service {

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_structured_data_product', array( self::class, 'add_properties' ), 10, 2 );
	}

	/**
	 * Append spec PropertyValues.
	 *
	 * @param array<string, mixed> $markup  Product markup.
	 * @param \WC_Product          $product Product.
	 * @return array<string, mixed>
	 */
	public static function add_properties( $markup, $product ): array {
		$markup = is_array( $markup ) ? $markup : array();
		if ( ! $product instanceof \WC_Product ) {
			return $markup;
		}
		// Specs of a password-protected product stay hidden until the password is entered (C15).
		$owner_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		if ( post_password_required( $owner_id ) ) {
			return $markup;
		}
		$specs = ProductSpecs::get( $product->get_id() );
		if ( ! $specs ) {
			return $markup;
		}

		$properties = isset( $markup['additionalProperty'] ) && is_array( $markup['additionalProperty'] ) ? $markup['additionalProperty'] : array();
		foreach ( $specs as $spec ) {
			$property = array(
				'@type' => 'PropertyValue',
				'name'  => $spec['label'],
				'value' => self::json_value( $spec['type'], $spec['value'] ),
			);
			if ( '' !== $spec['unit'] ) {
				$property['unitText'] = $spec['unit'];
			}
			$properties[] = $property;
		}
		$markup['additionalProperty'] = $properties;

		return $markup;
	}

	/**
	 * JSON-LD value for a stored spec value: numbers as numbers (string if not
	 * finite, L3), booleans as booleans, everything else as the string.
	 *
	 * @param string $type  Field type.
	 * @param string $value Stored value.
	 * @return int|float|bool|string
	 */
	public static function json_value( string $type, string $value ) {
		if ( 'number' === $type && is_numeric( $value ) ) {
			$number = 0 + $value;

			return is_finite( (float) $number ) ? $number : $value;
		}
		if ( 'boolean' === $type ) {
			return 'yes' === $value;
		}

		return $value;
	}
}
