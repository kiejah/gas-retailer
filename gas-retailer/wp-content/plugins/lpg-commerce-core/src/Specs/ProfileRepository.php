<?php
/**
 * Reads, validates and formats spec profile field definitions and values.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Specs;

defined( 'ABSPATH' ) || exit;

/**
 * Field definitions live in `_lpg_fields` on an `lpg_spec_profile` post (§11.3).
 *
 * Pure helpers: no hooks. The static methods are internal to the plugin; the
 * public PHP API is ProductSpecs::get() and ProfileResolver::for_product().
 */
final class ProfileRepository {

	public const FIELDS_META = '_lpg_fields';

	public const VALUE_META_PREFIX = '_lpg_spec_';

	public const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,27}$/';

	public const TYPES = array( 'text', 'number', 'select', 'boolean' );

	/**
	 * Keys that would collide with other `_lpg_spec_*` meta (L2).
	 * `profile_id` → `_lpg_spec_profile_id`, the product override.
	 */
	public const RESERVED_KEYS = array( 'profile_id' );

	/**
	 * Maximum significant digits accepted in a number value (L3).
	 */
	public const MAX_NUMBER_DIGITS = 20;

	/**
	 * Whether a post ID is a usable spec profile: exists, right type, not trashed (C2).
	 *
	 * @param int $profile_id Post ID.
	 */
	public static function is_profile( int $profile_id ): bool {
		if ( $profile_id <= 0 ) {
			return false;
		}
		$post = get_post( $profile_id );

		return $post instanceof \WP_Post
			&& ProfilePostType::POST_TYPE === $post->post_type
			&& ! in_array( $post->post_status, array( 'trash', 'auto-draft' ), true );
	}

	/**
	 * Validated field definitions of a profile, in order. Invalid stored rows are skipped.
	 *
	 * @param int $profile_id Profile post ID.
	 * @return array<int, array{key: string, label: string, type: string, unit: string, options: string[], filterable: bool, help: string}>
	 */
	public static function get_fields( int $profile_id ): array {
		if ( ! self::is_profile( $profile_id ) ) {
			return array();
		}
		$stored = get_post_meta( $profile_id, self::FIELDS_META, true );
		if ( ! is_array( $stored ) ) {
			return array();
		}

		return self::validate_definitions( $stored )['fields'];
	}

	/**
	 * Validate raw field definition rows (from the admin form or storage).
	 *
	 * Completely blank rows are ignored. Invalid rows are dropped and reported (C1).
	 * Input must already be unslashed.
	 *
	 * @param array<int|string, mixed> $rows Raw rows.
	 * @return array{fields: array<int, array{key: string, label: string, type: string, unit: string, options: string[], filterable: bool, help: string}>, errors: string[]}
	 */
	public static function validate_definitions( array $rows ): array {
		$fields = array();
		$errors = array();
		$seen   = array();
		$number = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key   = isset( $row['key'] ) && is_scalar( $row['key'] ) ? trim( (string) $row['key'] ) : '';
			$label = isset( $row['label'] ) && is_scalar( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
			if ( '' === $key && '' === $label ) {
				continue;
			}
			++$number;
			$type = isset( $row['type'] ) && is_scalar( $row['type'] ) ? (string) $row['type'] : '';

			/* translators: 1: row number, 2: field key as entered. */
			$prefix = sprintf( __( 'Row %1$d (%2$s):', 'lpg-commerce-core' ), $number, '' === $key ? '-' : sanitize_text_field( $key ) );

			if ( ! preg_match( self::KEY_PATTERN, $key ) ) {
				$errors[] = $prefix . ' ' . __( 'the key must start with a lowercase letter and use only lowercase letters, digits and underscores (28 characters at most).', 'lpg-commerce-core' );
				continue;
			}
			if ( in_array( $key, self::RESERVED_KEYS, true ) ) {
				$errors[] = $prefix . ' ' . __( 'this key is reserved by LPG Commerce and cannot be used.', 'lpg-commerce-core' );
				continue;
			}
			if ( isset( $seen[ $key ] ) ) {
				$errors[] = $prefix . ' ' . __( 'this key is already used in this profile.', 'lpg-commerce-core' );
				continue;
			}
			if ( '' === $label ) {
				$errors[] = $prefix . ' ' . __( 'a label is required.', 'lpg-commerce-core' );
				continue;
			}
			if ( ! in_array( $type, self::TYPES, true ) ) {
				$errors[] = $prefix . ' ' . __( 'the type must be text, number, select or yes/no.', 'lpg-commerce-core' );
				continue;
			}

			$options = array();
			if ( 'select' === $type ) {
				$options = self::parse_options( $row['options'] ?? array() );
				if ( ! $options ) {
					$errors[] = $prefix . ' ' . __( 'a select field needs at least one option.', 'lpg-commerce-core' );
					continue;
				}
			}

			$filterable = ! empty( $row['filterable'] );
			if ( $filterable && function_exists( 'wc_check_if_attribute_name_is_reserved' ) && wc_check_if_attribute_name_is_reserved( $key ) ) {
				$errors[] = $prefix . ' ' . __( 'this key is reserved by WordPress and cannot be used for a filterable field.', 'lpg-commerce-core' );
				continue;
			}

			$seen[ $key ] = true;
			$fields[]     = array(
				'key'        => $key,
				'label'      => $label,
				'type'       => $type,
				'unit'       => isset( $row['unit'] ) && is_scalar( $row['unit'] ) ? sanitize_text_field( (string) $row['unit'] ) : '',
				'options'    => $options,
				'filterable' => $filterable,
				'help'       => isset( $row['help'] ) && is_scalar( $row['help'] ) ? sanitize_text_field( (string) $row['help'] ) : '',
			);
		}//end foreach

		return array(
			'fields' => $fields,
			'errors' => $errors,
		);
	}

	/**
	 * Sanitise a raw value for a field to its stored form (§11.3, C3, C4).
	 *
	 * @param array{type: string, options?: string[]} $field Field definition.
	 * @param mixed                                   $raw   Raw (unslashed) value.
	 * @return array{value: string, error: bool} Empty value = not set. `error` is true when a non-empty input was rejected.
	 */
	public static function sanitize_value( array $field, $raw ): array {
		if ( is_bool( $raw ) && 'boolean' === $field['type'] ) {
			return array(
				'value' => $raw ? 'yes' : 'no',
				'error' => false,
			);
		}
		if ( ! is_scalar( $raw ) ) {
			return array(
				'value' => '',
				'error' => null !== $raw,
			);
		}
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return array(
				'value' => '',
				'error' => false,
			);
		}

		switch ( $field['type'] ) {
			case 'number':
				$value = self::normalize_number( $raw );
				break;
			case 'select':
				$value = in_array( $raw, (array) ( $field['options'] ?? array() ), true ) ? $raw : '';
				break;
			case 'boolean':
				$value = in_array( $raw, array( 'yes', 'no' ), true ) ? $raw : '';
				break;
			default:
				$value = sanitize_text_field( $raw );
		}

		return array(
			'value' => $value,
			'error' => '' === $value,
		);
	}

	/**
	 * Human-readable form of a stored value. Unescaped.
	 *
	 * @param array{type: string, unit?: string} $field Field definition.
	 * @param string                             $value Stored value (non-empty).
	 */
	public static function format_display( array $field, string $value ): string {
		if ( 'boolean' === $field['type'] ) {
			return 'yes' === $value ? __( 'Yes', 'lpg-commerce-core' ) : __( 'No', 'lpg-commerce-core' );
		}
		$unit = (string) ( $field['unit'] ?? '' );
		if ( 'number' === $field['type'] && '' !== $unit ) {
			return $value . ' ' . $unit;
		}

		return $value;
	}

	/**
	 * Meta key holding a field's value.
	 *
	 * @param string $key Field key.
	 */
	public static function value_meta_key( string $key ): string {
		return self::VALUE_META_PREFIX . $key;
	}

	/**
	 * Normalise a number: `2,5` → `2.5`; negatives allowed; anything else rejected.
	 *
	 * @param string $raw Trimmed input.
	 * @return string Numeric string, or '' if invalid.
	 */
	private static function normalize_number( string $raw ): string {
		/*
		 * A single comma is a decimal separator only when there is no dot.
		 * "1,000" (non-zero integer part + exactly 3 digits) reads as a
		 * thousands separator, so it is rejected as ambiguous.
		 */
		if ( false === strpos( $raw, '.' ) && 1 === substr_count( $raw, ',' ) ) {
			if ( preg_match( '/^-?(0*[1-9]\d*),\d{3}$/', $raw ) ) {
				return '';
			}
			$raw = str_replace( ',', '.', $raw );
		}
		if ( ! preg_match( '/^-?\d+(\.\d+)?$/', $raw ) ) {
			return '';
		}
		$digits = ltrim( str_replace( array( '-', '.' ), '', $raw ), '0' );
		if ( strlen( $digits ) > self::MAX_NUMBER_DIGITS ) {
			return '';
		}

		return $raw;
	}

	/**
	 * Parse select options from a textarea string or a list.
	 *
	 * @param mixed $raw One option per line, or an array.
	 * @return string[]
	 */
	private static function parse_options( $raw ): array {
		if ( is_string( $raw ) ) {
			$raw = preg_split( '/\r\n|\r|\n/', $raw );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$options = array();
		foreach ( $raw as $option ) {
			if ( ! is_scalar( $option ) ) {
				continue;
			}
			$option = sanitize_text_field( (string) $option );
			if ( '' !== $option && ! in_array( $option, $options, true ) ) {
				$options[] = $option;
			}
		}

		return $options;
	}
}
