<?php
/**
 * Quote request (RFQ) records.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Enquiries;

defined( 'ABSPATH' ) || exit;

/**
 * `lpg_rfq` (§13.3): quote requests from the RFQ form.
 */
final class RfqPostType extends RecordType {

	public const POST_TYPE = 'lpg_rfq';

	public const META_CONTACT      = '_lpg_contact';
	public const META_SEGMENT      = '_lpg_segment';
	public const META_ITEMS        = '_lpg_items';
	public const META_REQUIREMENTS = '_lpg_requirements';
	public const META_PROJECT      = '_lpg_project';
	public const META_LOCATION     = '_lpg_location';
	public const META_COMMENTS     = '_lpg_comments';
	public const META_CONSENT      = '_lpg_consent';

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
		return array( 'lpg_rfq', 'lpg_rfqs' );
	}

	/**
	 * Statuses in workflow order.
	 *
	 * @return string[]
	 */
	public static function statuses(): array {
		return array( Statuses::NEW, Statuses::REVIEW, Statuses::QUOTED, Statuses::CLOSED );
	}

	/**
	 * Labels.
	 *
	 * @return array<string, string>
	 */
	protected static function labels(): array {
		return array(
			'name'               => __( 'Quote requests', 'lpg-commerce-core' ),
			'singular_name'      => __( 'Quote request', 'lpg-commerce-core' ),
			'menu_name'          => __( 'Quote requests', 'lpg-commerce-core' ),
			'all_items'          => __( 'Quote requests', 'lpg-commerce-core' ),
			'edit_item'          => __( 'Quote request', 'lpg-commerce-core' ),
			'view_item'          => __( 'View quote request', 'lpg-commerce-core' ),
			'search_items'       => __( 'Search quote requests', 'lpg-commerce-core' ),
			'not_found'          => __( 'No quote requests yet.', 'lpg-commerce-core' ),
			'not_found_in_trash' => __( 'No quote requests in the bin.', 'lpg-commerce-core' ),
		);
	}

	/**
	 * Create a quote request from validated, sanitised data.
	 *
	 * @param array<string, mixed> $data Keys: contact, segment, items, requirements, project, location, comments, consent.
	 * @return int|\WP_Error Post ID.
	 */
	public static function create( array $data ) {
		$contact = (array) ( $data['contact'] ?? array() );
		return self::insert(
			array(
				self::META_CONTACT      => $contact,
				self::META_SEGMENT      => (string) ( $data['segment'] ?? '' ),
				self::META_ITEMS        => (array) ( $data['items'] ?? array() ),
				self::META_REQUIREMENTS => (string) ( $data['requirements'] ?? '' ),
				self::META_PROJECT      => (string) ( $data['project'] ?? '' ),
				self::META_LOCATION     => (string) ( $data['location'] ?? '' ),
				self::META_COMMENTS     => (string) ( $data['comments'] ?? '' ),
				self::META_CONSENT      => (array) ( $data['consent'] ?? array() ),
			),
			static function ( int $id ) use ( $contact ): string {
				$who = '' !== (string) ( $contact['organisation'] ?? '' ) ? $contact['organisation'] : ( $contact['name'] ?? '' );
				/* translators: 1: record ID, 2: organisation or name. */
				return sprintf( __( 'RFQ #%1$d: %2$s', 'lpg-commerce-core' ), $id, $who );
			}
		);
	}

	/**
	 * Read a record's data with safe defaults.
	 *
	 * @param int $post_id Post ID.
	 * @return array{contact: array{name: string, organisation: string, phone: string, email: string}, segment: string, items: array<int, array{product_id: int, description: string, qty: int}>, requirements: string, project: string, location: string, comments: string, consent: array{at: string, policy_version: string}}
	 */
	public static function get( int $post_id ): array {
		$contact = get_post_meta( $post_id, self::META_CONTACT, true );
		$contact = is_array( $contact ) ? $contact : array();
		$items   = get_post_meta( $post_id, self::META_ITEMS, true );
		$consent = get_post_meta( $post_id, self::META_CONSENT, true );
		$consent = is_array( $consent ) ? $consent : array();

		$clean_items = array();
		foreach ( is_array( $items ) ? $items : array() as $item ) {
			if ( is_array( $item ) ) {
				$clean_items[] = array(
					'product_id'  => (int) ( $item['product_id'] ?? 0 ),
					'description' => self::str( $item['description'] ?? '' ),
					'qty'         => (int) ( $item['qty'] ?? 0 ),
				);
			}
		}

		return array(
			'contact'      => array(
				'name'         => self::str( $contact['name'] ?? '' ),
				'organisation' => self::str( $contact['organisation'] ?? '' ),
				'phone'        => self::str( $contact['phone'] ?? '' ),
				'email'        => self::str( $contact['email'] ?? '' ),
			),
			'segment'      => self::str( get_post_meta( $post_id, self::META_SEGMENT, true ) ),
			'items'        => $clean_items,
			'requirements' => self::str( get_post_meta( $post_id, self::META_REQUIREMENTS, true ) ),
			'project'      => self::str( get_post_meta( $post_id, self::META_PROJECT, true ) ),
			'location'     => self::str( get_post_meta( $post_id, self::META_LOCATION, true ) ),
			'comments'     => self::str( get_post_meta( $post_id, self::META_COMMENTS, true ) ),
			'consent'      => array(
				'at'             => self::str( $consent['at'] ?? '' ),
				'policy_version' => self::str( $consent['policy_version'] ?? '' ),
			),
		);
	}

	/**
	 * Scalar to string, anything else to ''.
	 *
	 * @param mixed $value Value.
	 */
	private static function str( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}
}
