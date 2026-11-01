<?php
/**
 * Retention clean-up for quote requests and enquiries.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Privacy;

use LPG\Commerce\Enquiries\EnquiryPostType;
use LPG\Commerce\Enquiries\RfqPostType;
use LPG\Commerce\Enquiries\Statuses;
use LPG\Commerce\Service;
use LPG\Commerce\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Daily WP-Cron job (§13.8): permanently deletes RFQs and enquiries older than
 * `enquiry_retention_months` whose status is `lpg-new`, `lpg-handled` or
 * `lpg-closed`. Records in review or quoted are kept. Does nothing when the
 * setting is 0, the default until the client approves a period (W-007).
 *
 * Scheduled on activation (and on install/upgrade if missing), cleared on
 * deactivation and uninstall. Deletes at most BATCH records per run, so a
 * large backlog is cleared over several days without a long request.
 */
final class Retention implements Service {

	public const HOOK = 'lpg_retention_cleanup';

	public const BATCH = 200;

	public const STATUSES = array( Statuses::NEW, Statuses::HANDLED, Statuses::CLOSED );

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( self::HOOK, array( self::class, 'run' ) );
	}

	/**
	 * Schedule the daily event if it is not scheduled.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Remove the event.
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Delete expired records.
	 *
	 * @return int Number of records deleted.
	 */
	public static function run(): int {
		$months = (int) Settings::get( 'enquiry_retention_months' );
		if ( $months <= 0 ) {
			return 0;
		}

		$ids = get_posts(
			array(
				'post_type'        => array( RfqPostType::POST_TYPE, EnquiryPostType::POST_TYPE ),
				'post_status'      => self::STATUSES,
				'fields'           => 'ids',
				'posts_per_page'   => self::BATCH,
				'orderby'          => 'date',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'date_query'       => array(
					array(
						'column' => 'post_date_gmt',
						'before' => gmdate( 'Y-m-d H:i:s', (int) strtotime( "-{$months} months" ) ),
					),
				),
			)
		);

		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( wp_delete_post( (int) $id, true ) ) {
				++$deleted;
			}
		}
		return $deleted;
	}
}
