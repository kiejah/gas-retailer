<?php
/**
 * Logged WhatsApp enquiry records.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Enquiries;

defined( 'ABSPATH' ) || exit;

/**
 * `lpg_enquiry` (§13.3): WhatsApp product and exchange enquiries the visitor
 * *initiated* (R7). Whether the message was actually sent is unknown.
 * Holds no name, phone or email: the conversation itself is on WhatsApp.
 */
final class EnquiryPostType extends RecordType {

	public const POST_TYPE = 'lpg_enquiry';

	public const TYPE_PRODUCT  = 'product';
	public const TYPE_EXCHANGE = 'exchange';

	public const EXCHANGE_TYPES = array( 'exchange', 'new' );

	public const META_TYPE          = '_lpg_type';
	public const META_PRODUCT_ID    = '_lpg_product_id';
	public const META_BRAND         = '_lpg_brand';
	public const META_SIZE          = '_lpg_size';
	public const META_EXCHANGE_TYPE = '_lpg_exchange_type';
	public const META_LOCATION      = '_lpg_location';

	/**
	 * Post type slug.
	 */
	public static function post_type(): string {
		return self::POST_TYPE;
	}

	/**
	 * Capability type.
	 *
	 * @return array{0: string, 1: string}
	 */
	protected static function capability_type(): array {
		return array( 'lpg_enquiry', 'lpg_enquiries' );
	}

	/**
	 * Statuses in workflow order.
	 *
	 * @return string[]
	 */
	public static function statuses(): array {
		return array( Statuses::NEW, Statuses::HANDLED );
	}

	/**
	 * Labels.
	 *
	 * @return array<string, string>
	 */
	protected static function labels(): array {
		return array(
			'name'               => __( 'WhatsApp enquiries', 'lpg-commerce-core' ),
			'singular_name'      => __( 'WhatsApp enquiry', 'lpg-commerce-core' ),
			'menu_name'          => __( 'WhatsApp enquiries', 'lpg-commerce-core' ),
			'all_items'          => __( 'WhatsApp enquiries', 'lpg-commerce-core' ),
			'edit_item'          => __( 'WhatsApp enquiry', 'lpg-commerce-core' ),
			'view_item'          => __( 'View WhatsApp enquiry', 'lpg-commerce-core' ),
			'search_items'       => __( 'Search WhatsApp enquiries', 'lpg-commerce-core' ),
			'not_found'          => __( 'No WhatsApp enquiries yet.', 'lpg-commerce-core' ),
			'not_found_in_trash' => __( 'No WhatsApp enquiries in the bin.', 'lpg-commerce-core' ),
		);
	}

	/**
	 * Translated label for an enquiry type.
	 *
	 * @param string $type product|exchange.
	 */
	public static function type_label( string $type ): string {
		return self::TYPE_EXCHANGE === $type ? __( 'Cylinder refill / exchange', 'lpg-commerce-core' ) : __( 'Product', 'lpg-commerce-core' );
	}

	/**
	 * Translated label for an exchange type (also used as `{type}` in the message).
	 *
	 * @param string $exchange_type exchange|new.
	 */
	public static function exchange_type_label( string $exchange_type ): string {
		return 'new' === $exchange_type ? __( 'purchase (new cylinder)', 'lpg-commerce-core' ) : __( 'exchange', 'lpg-commerce-core' );
	}

	/**
	 * Create an enquiry from validated, sanitised data.
	 *
	 * The brand is stored by name (not slug) so the record stays readable if
	 * the brand is later renamed or deleted.
	 *
	 * @param array<string, mixed> $data Keys: type, product_id, brand (name), size, exchange_type, location.
	 * @return int|\WP_Error Post ID.
	 */
	public static function create( array $data ) {
		$type = self::TYPE_EXCHANGE === ( $data['type'] ?? '' ) ? self::TYPE_EXCHANGE : self::TYPE_PRODUCT;
		$meta = array(
			self::META_TYPE     => $type,
			self::META_LOCATION => (string) ( $data['location'] ?? '' ),
		);
		if ( self::TYPE_PRODUCT === $type ) {
			$meta[ self::META_PRODUCT_ID ] = (int) ( $data['product_id'] ?? 0 );
		} else {
			$meta[ self::META_BRAND ]         = (string) ( $data['brand'] ?? '' );
			$meta[ self::META_SIZE ]          = (string) ( $data['size'] ?? '' );
			$meta[ self::META_EXCHANGE_TYPE ] = (string) ( $data['exchange_type'] ?? '' );
		}

		return self::insert(
			$meta,
			static function ( int $id ) use ( $type, $data ): string {
				if ( self::TYPE_PRODUCT === $type ) {
					$subject = get_the_title( (int) ( $data['product_id'] ?? 0 ) );
				} else {
					$subject = trim( ( $data['brand'] ?? '' ) . ' ' . ( $data['size'] ?? '' ) );
				}
				/* translators: 1: record ID, 2: enquiry type, 3: product or brand and size. */
				return sprintf( __( 'Enquiry #%1$d: %2$s, %3$s', 'lpg-commerce-core' ), $id, self::type_label( $type ), html_entity_decode( (string) $subject, ENT_QUOTES, 'UTF-8' ) );
			}
		);
	}

	/**
	 * Read a record's data with safe defaults.
	 *
	 * @param int $post_id Post ID.
	 * @return array{type: string, product_id: int, brand: string, size: string, exchange_type: string, location: string}
	 */
	public static function get( int $post_id ): array {
		$str = static function ( string $key ) use ( $post_id ): string {
			$value = get_post_meta( $post_id, $key, true );
			return is_scalar( $value ) ? (string) $value : '';
		};
		return array(
			'type'          => $str( self::META_TYPE ),
			'product_id'    => (int) $str( self::META_PRODUCT_ID ),
			'brand'         => $str( self::META_BRAND ),
			'size'          => $str( self::META_SIZE ),
			'exchange_type' => $str( self::META_EXCHANGE_TYPE ),
			'location'      => $str( self::META_LOCATION ),
		);
	}
}
