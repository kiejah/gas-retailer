<?php
/**
 * Email notifications for new records.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Enquiries;

use LPG\Commerce\Service;
use LPG\Commerce\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Sends §13.7 notifications when a record is first inserted:
 * - new quote request: To `notify_sales_email`, Cc `notify_owner_email`,
 *   Reply-To the customer;
 * - new exchange enquiry: a short email To Sales;
 * - product enquiries: logged only, no email.
 *
 * Plain text only. Anything that reaches a header or the subject has CR/LF
 * and header-significant characters removed. Mail failures are swallowed:
 * the record is already saved and the visitor's request must not fail.
 * Uses the core `wp_after_insert_post` hook (meta is saved by then), so no
 * new plugin hook is introduced.
 */
final class Notifications implements Service {

	/**
	 * Whether notifications are suppressed for records created right now
	 * (site-wide ceiling exceeded, C22 M1b).
	 *
	 * @var bool
	 */
	private static bool $suppressed = false;

	/**
	 * Run a callback with record notifications suppressed.
	 *
	 * @param callable $callback Callback.
	 * @return mixed The callback's return value.
	 */
	public static function suppressed( callable $callback ) {
		$previous         = self::$suppressed;
		self::$suppressed = true;
		try {
			return $callback();
		} finally {
			self::$suppressed = $previous;
		}
	}

	/**
	 * Tell Sales that submissions passed the hourly ceiling. Counts only,
	 * no customer data. The caller ensures one alert per route per hour.
	 *
	 * @param string $route Route, e.g. 'rfq'.
	 * @param int    $count Estimated submissions in the last hour.
	 * @param int    $limit Ceiling.
	 * @return bool Whether wp_mail() reported success.
	 */
	public static function send_volume_alert( string $route, int $count, int $limit ): bool {
		$to = self::recipient( 'notify_sales_email' );
		if ( '' === $to ) {
			$to = self::recipient( 'notify_owner_email' );
		}
		if ( '' === $to ) {
			return false;
		}
		$label = 'rfq' === $route ? __( 'quote requests', 'lpg-commerce-core' ) : __( 'WhatsApp enquiries', 'lpg-commerce-core' );
		/* translators: 1: site name, 2: record type. */
		$subject = self::header_safe( sprintf( __( '[%1$s] Unusual volume of %2$s', 'lpg-commerce-core' ), self::site_name(), $label ) );
		$lines   = array(
			/* translators: 1: number of submissions, 2: record type, 3: hourly limit. */
			sprintf( __( 'About %1$d %2$s were submitted in the last hour (the alert level is %3$d per hour).', 'lpg-commerce-core' ), $count, $label, $limit ),
			'',
			__( 'All of them are saved, but email notifications for new ones are paused for the rest of this hour. Please review the list in the dashboard; this may be spam.', 'lpg-commerce-core' ),
			'',
			__( 'View in the dashboard:', 'lpg-commerce-core' ) . ' ' . admin_url( 'edit.php?post_type=' . ( 'rfq' === $route ? RfqPostType::POST_TYPE : EnquiryPostType::POST_TYPE ) ),
		);
		return self::mail( $to, $subject, $lines, array() );
	}

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'wp_after_insert_post', array( $this, 'on_insert' ), 10, 3 );
	}

	/**
	 * Dispatch on first insert of a record.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 * @param bool     $update  Whether this is an update.
	 */
	public function on_insert( $post_id, $post, $update ): void {
		if ( $update || self::$suppressed || ! $post instanceof \WP_Post ) {
			return;
		}
		try {
			if ( RfqPostType::POST_TYPE === $post->post_type ) {
				self::send_rfq( (int) $post_id );
			} elseif ( EnquiryPostType::POST_TYPE === $post->post_type ) {
				self::send_enquiry( (int) $post_id );
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Never break the visitor's request over email.
		}
	}

	/**
	 * Email a new quote request.
	 *
	 * @param int $post_id RFQ ID.
	 * @return bool Whether wp_mail() reported success (false also when no recipient).
	 */
	public static function send_rfq( int $post_id ): bool {
		$to = self::recipient( 'notify_sales_email' );
		$cc = self::recipient( 'notify_owner_email' );
		if ( '' === $to ) {
			$to = $cc;
			$cc = '';
		}
		if ( '' === $to ) {
			return false;
		}

		$rfq     = RfqPostType::get( $post_id );
		$contact = $rfq['contact'];
		$who     = '' !== $contact['organisation'] ? $contact['organisation'] : $contact['name'];

		$headers = array();
		if ( '' !== $cc && strcasecmp( $cc, $to ) !== 0 ) {
			$headers[] = 'Cc: ' . $cc;
		}
		// Reply-To only for a clean, valid address: never "repair" a hostile one.
		$reply_email = $contact['email'];
		if ( '' !== $reply_email && self::header_safe( $reply_email ) === $reply_email && is_email( $reply_email ) && sanitize_email( $reply_email ) === $reply_email ) {
			$reply_name = trim( (string) preg_replace( '/[<>"\\\\,;:@]+/', '', self::header_safe( $contact['name'] ) ) );
			$headers[]  = 'Reply-To: ' . ( '' !== $reply_name ? $reply_name . ' <' . $reply_email . '>' : $reply_email );
		}

		/* translators: 1: site name, 2: record ID, 3: organisation or name. */
		$subject = self::header_safe( sprintf( __( '[%1$s] New quote request #%2$d: %3$s', 'lpg-commerce-core' ), self::site_name(), $post_id, $who ) );

		$lines = array(
			__( 'A new quote request was submitted on the website.', 'lpg-commerce-core' ),
			'',
			__( 'Name:', 'lpg-commerce-core' ) . ' ' . $contact['name'],
			__( 'Organisation:', 'lpg-commerce-core' ) . ' ' . $contact['organisation'],
			__( 'Phone:', 'lpg-commerce-core' ) . ' +' . $contact['phone'],
			__( 'Email:', 'lpg-commerce-core' ) . ' ' . $contact['email'],
			__( 'Segment:', 'lpg-commerce-core' ) . ' ' . $rfq['segment'],
			'',
			__( 'Items:', 'lpg-commerce-core' ),
		);
		foreach ( $rfq['items'] as $item ) {
			$lines[] = sprintf( '- %d × %s', $item['qty'], $item['description'] ) . ( $item['product_id'] > 0 ? ' (' . get_permalink( $item['product_id'] ) . ')' : '' );
		}
		foreach (
			array(
				__( 'Requirements:', 'lpg-commerce-core' ) => $rfq['requirements'],
				__( 'Project:', 'lpg-commerce-core' )      => $rfq['project'],
				__( 'Location:', 'lpg-commerce-core' )     => $rfq['location'],
				__( 'Comments:', 'lpg-commerce-core' )     => $rfq['comments'],
			) as $label => $value
		) {
			if ( '' !== $value ) {
				$lines[] = '';
				$lines[] = $label . ' ' . $value;
			}
		}
		$lines[] = '';
		$lines[] = __( 'View in the dashboard:', 'lpg-commerce-core' ) . ' ' . self::admin_link( $post_id );

		return self::mail( $to, $subject, $lines, $headers );
	}

	/**
	 * Email a new exchange enquiry (product enquiries are logged only).
	 *
	 * @param int $post_id Enquiry ID.
	 * @return bool Whether an email was sent.
	 */
	public static function send_enquiry( int $post_id ): bool {
		$enquiry = EnquiryPostType::get( $post_id );
		if ( EnquiryPostType::TYPE_EXCHANGE !== $enquiry['type'] ) {
			return false;
		}
		$to = self::recipient( 'notify_sales_email' );
		if ( '' === $to ) {
			$to = self::recipient( 'notify_owner_email' );
		}
		if ( '' === $to ) {
			return false;
		}

		/* translators: 1: site name, 2: record ID, 3: brand, 4: size. */
		$subject = self::header_safe( sprintf( __( '[%1$s] Cylinder request #%2$d: %3$s %4$s', 'lpg-commerce-core' ), self::site_name(), $post_id, $enquiry['brand'], $enquiry['size'] ) );
		$lines   = array(
			__( 'A visitor started a cylinder refill / exchange request on WhatsApp. Check WhatsApp for their message (they may not have sent it).', 'lpg-commerce-core' ),
			'',
			__( 'Brand:', 'lpg-commerce-core' ) . ' ' . $enquiry['brand'],
			__( 'Size:', 'lpg-commerce-core' ) . ' ' . $enquiry['size'],
			__( 'Request:', 'lpg-commerce-core' ) . ' ' . EnquiryPostType::exchange_type_label( $enquiry['exchange_type'] ),
			__( 'Delivery location:', 'lpg-commerce-core' ) . ' ' . $enquiry['location'],
			'',
			__( 'View in the dashboard:', 'lpg-commerce-core' ) . ' ' . self::admin_link( $post_id ),
		);
		return self::mail( $to, $subject, $lines, array() );
	}

	/**
	 * A validated recipient from settings, or ''.
	 *
	 * @param string $key Settings key.
	 */
	private static function recipient( string $key ): string {
		$email = self::header_safe( (string) Settings::get( $key ) );
		return '' !== $email && is_email( $email ) ? $email : '';
	}

	/**
	 * Remove CR, LF and other control characters (header injection).
	 *
	 * @param string $value Value.
	 */
	public static function header_safe( string $value ): string {
		return trim( (string) preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $value ) );
	}

	/**
	 * Site name as plain text.
	 */
	private static function site_name(): string {
		return wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * Dashboard link (does not depend on the current user's caps).
	 *
	 * @param int $post_id Post ID.
	 */
	private static function admin_link( int $post_id ): string {
		return admin_url( 'post.php?post=' . $post_id . '&action=edit' );
	}

	/**
	 * Send a plain-text email; never throws.
	 *
	 * @param string   $to      Recipient.
	 * @param string   $subject Subject (header-safe).
	 * @param string[] $lines   Body lines.
	 * @param string[] $headers Headers.
	 */
	private static function mail( string $to, string $subject, array $lines, array $headers ): bool {
		$headers[] = 'Content-Type: text/plain; charset=UTF-8';
		$body      = implode( "\n", array_map( static fn( $line ): string => wp_strip_all_tags( (string) $line, false ), $lines ) );
		try {
			return (bool) wp_mail( $to, $subject, $body, $headers );
		} catch ( \Throwable $e ) {
			return false;
		}
	}
}
