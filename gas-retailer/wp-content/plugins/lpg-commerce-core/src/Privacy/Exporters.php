<?php
/**
 * Personal data exporter and eraser for quote requests.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Privacy;

use LPG\Commerce\Enquiries\RfqPostType;
use LPG\Commerce\Enquiries\Statuses;
use LPG\Commerce\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks quote requests into Tools → Export / Erase Personal Data (§13.8).
 *
 * Matching: by the email stored in `_lpg_contact` only. WordPress's privacy
 * tools pass an email address, and every RFQ requires an email, so this finds
 * every RFQ the person submitted under that address. Matching by phone is not
 * done: the tools never supply a phone, and a shared office or family number
 * would pull in other people's requests.
 *
 * Enquiries (`lpg_enquiry`) store no name, email or phone (§13.3): only the
 * product or cylinder choice and a delivery area. Nothing in them can be tied
 * to a requester's email, so no exporter/eraser is registered for them; the
 * retention cron removes them over time.
 *
 * Erasing deletes the matching RFQ records outright (they hold nothing but the
 * request itself).
 */
final class Exporters implements Service {

	public const ID       = 'lpg-commerce-rfq';
	public const PER_PAGE = 20;

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'add_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'add_eraser' ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array<string, array<string, mixed>> $exporters Exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_exporter( $exporters ): array {
		$exporters             = is_array( $exporters ) ? $exporters : array();
		$exporters[ self::ID ] = array(
			'exporter_friendly_name' => __( 'Quote requests', 'lpg-commerce-core' ),
			'callback'               => array( self::class, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array<string, array<string, mixed>> $erasers Erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_eraser( $erasers ): array {
		$erasers             = is_array( $erasers ) ? $erasers : array();
		$erasers[ self::ID ] = array(
			'eraser_friendly_name' => __( 'Quote requests', 'lpg-commerce-core' ),
			'callback'             => array( self::class, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export one page of RFQs for an email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page, from 1.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public static function export( $email, $page = 1 ): array {
		[ $ids, $raw ] = self::find( (string) $email, (int) $page );
		$data          = array();
		foreach ( $ids as $id ) {
			$rfq   = RfqPostType::get( $id );
			$items = array();
			foreach ( $rfq['items'] as $item ) {
				$items[] = $item['qty'] . ' × ' . $item['description'];
			}
			$fields = array(
				__( 'Submitted', 'lpg-commerce-core' )     => (string) get_post_field( 'post_date', $id ),
				__( 'Status', 'lpg-commerce-core' )        => Statuses::label( (string) get_post_status( $id ) ),
				__( 'Name', 'lpg-commerce-core' )          => $rfq['contact']['name'],
				__( 'Organisation', 'lpg-commerce-core' )  => $rfq['contact']['organisation'],
				__( 'Phone', 'lpg-commerce-core' )         => $rfq['contact']['phone'],
				__( 'Email', 'lpg-commerce-core' )         => $rfq['contact']['email'],
				__( 'Segment', 'lpg-commerce-core' )       => $rfq['segment'],
				__( 'Items', 'lpg-commerce-core' )         => implode( "\n", $items ),
				__( 'Requirements', 'lpg-commerce-core' )  => $rfq['requirements'],
				__( 'Project', 'lpg-commerce-core' )       => $rfq['project'],
				__( 'Location', 'lpg-commerce-core' )      => $rfq['location'],
				__( 'Comments', 'lpg-commerce-core' )      => $rfq['comments'],
				__( 'Consent given', 'lpg-commerce-core' ) => $rfq['consent']['at'],
				__( 'Privacy policy version', 'lpg-commerce-core' ) => $rfq['consent']['policy_version'],
			);
			$rows   = array();
			foreach ( $fields as $name => $value ) {
				if ( '' !== $value ) {
					$rows[] = array(
						'name'  => $name,
						'value' => $value,
					);
				}
			}
			$data[] = array(
				'group_id'    => 'lpg-quote-requests',
				'group_label' => __( 'Quote requests', 'lpg-commerce-core' ),
				'item_id'     => 'lpg-rfq-' . $id,
				'data'        => $rows,
			);
		}//end foreach
		return array(
			'data' => $data,
			'done' => $raw < self::PER_PAGE,
		);
	}

	/**
	 * Erase (delete) one page of RFQs for an email address.
	 *
	 * Always reads page 1: deleted records drop out of the next query.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page (unused, see above).
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public static function erase( $email, $page = 1 ): array {
		unset( $page );
		[ $ids, $raw ] = self::find( (string) $email, 1 );
		$removed       = false;
		$kept          = false;
		foreach ( $ids as $id ) {
			if ( wp_delete_post( $id, true ) ) {
				$removed = true;
			} else {
				$kept = true;
			}
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => $kept,
			'messages'       => $kept ? array( __( 'Some quote requests could not be deleted.', 'lpg-commerce-core' ) ) : array(),
			'done'           => $raw < self::PER_PAGE || $kept || ! $removed,
		);
	}

	/**
	 * RFQ IDs whose contact email matches (case-insensitive), one page.
	 *
	 * The contact is a serialized array, so the query narrows with LIKE on the
	 * serialized email string and PHP confirms the exact field match.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array{0: int[], 1: int} Matching IDs and the unfiltered page size.
	 */
	private static function find( string $email, int $page ): array {
		$email = trim( $email );
		if ( '' === $email || ! is_email( $email ) ) {
			return array( array(), 0 );
		}
		$ids = get_posts(
			array(
				'post_type'        => RfqPostType::POST_TYPE,
				// Explicit: 'any' skips statuses excluded from search, as ours are.
				'post_status'      => array_merge( RfqPostType::statuses(), array( 'trash' ) ),
				'fields'           => 'ids',
				'posts_per_page'   => self::PER_PAGE,
				'paged'            => max( 1, $page ),
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only privacy tool.
					array(
						'key'     => RfqPostType::META_CONTACT,
						'value'   => serialize( $email ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Matches the stored serialized form.
						'compare' => 'LIKE',
					),
				),
			)
		);
		$ids = array_map( 'intval', $ids );
		return array(
			array_values( array_filter( $ids, static fn( int $id ): bool => 0 === strcasecmp( RfqPostType::get( $id )['contact']['email'], $email ) ) ),
			count( $ids ),
		);
	}
}
