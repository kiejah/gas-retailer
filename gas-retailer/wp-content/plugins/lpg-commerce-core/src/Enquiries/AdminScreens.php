<?php
/**
 * Admin list and detail screens for quote requests and enquiries.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Enquiries;

use LPG\Commerce\Service;
use LPG\Commerce\Taxonomies\Segment;
use LPG\Commerce\WhatsApp\MessageBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * §13.3 admin: list columns and filters, read-only detail meta boxes and a
 * status change control. Both lists live under the WooCommerce menu
 * (`show_in_menu` = woocommerce), next to Orders, where shop managers work.
 *
 * Records are read-only: no editor, no title field, no Quick/Bulk Edit (which
 * would offer core statuses). The only change is the status, through a
 * nonce-protected admin-post action that checks `edit_post`.
 */
final class AdminScreens implements Service {

	public const STATUS_ACTION = 'lpg_set_status';

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		foreach ( self::types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'columns' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'column_content' ), 10, 2 );
			add_action( "add_meta_boxes_{$post_type}", array( $this, 'meta_boxes' ) );
			add_filter( "bulk_actions-edit-{$post_type}", array( $this, 'bulk_actions' ) );
		}
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'filters' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_list_query' ) );
		add_action( 'admin_post_' . self::STATUS_ACTION, array( $this, 'handle_status_request' ) );
		add_action( 'admin_notices', array( $this, 'status_notice' ) );
	}

	/**
	 * Record post types.
	 *
	 * @return string[]
	 */
	private static function types(): array {
		return array( RfqPostType::POST_TYPE, EnquiryPostType::POST_TYPE );
	}

	/**
	 * Statuses allowed for a post type.
	 *
	 * @param string $post_type Post type.
	 * @return string[]
	 */
	private static function statuses_for( string $post_type ): array {
		if ( RfqPostType::POST_TYPE === $post_type ) {
			return RfqPostType::statuses();
		}
		if ( EnquiryPostType::POST_TYPE === $post_type ) {
			return EnquiryPostType::statuses();
		}
		return array();
	}

	/**
	 * List columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( $columns ): array {
		$screen_type = self::current_list_type();
		$out         = array(
			'cb'    => $columns['cb'] ?? '<input type="checkbox" />',
			'title' => __( 'Record', 'lpg-commerce-core' ),
		);
		if ( EnquiryPostType::POST_TYPE === $screen_type ) {
			$out['lpg_type']     = __( 'Type', 'lpg-commerce-core' );
			$out['lpg_subject']  = __( 'Product / brand and size', 'lpg-commerce-core' );
			$out['lpg_location'] = __( 'Location', 'lpg-commerce-core' );
		} else {
			$out['lpg_contact'] = __( 'Name / organisation', 'lpg-commerce-core' );
			$out['lpg_segment'] = __( 'Segment', 'lpg-commerce-core' );
		}
		$out['lpg_status'] = __( 'Status', 'lpg-commerce-core' );
		$out['date']       = $columns['date'] ?? __( 'Date', 'lpg-commerce-core' );
		return $out;
	}

	/**
	 * Post type of the list being rendered (from the current screen or query).
	 */
	private static function current_list_type(): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && $screen->post_type ) {
			return $screen->post_type;
		}
		global $typenow;
		return is_string( $typenow ) ? $typenow : '';
	}

	/**
	 * Column content (escaped).
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 */
	public function column_content( $column, $post_id ): void {
		$post_id = (int) $post_id;
		switch ( $column ) {
			case 'lpg_contact':
				$contact = RfqPostType::get( $post_id )['contact'];
				echo esc_html( $contact['name'] );
				if ( '' !== $contact['organisation'] ) {
					echo '<br><span class="description">' . esc_html( $contact['organisation'] ) . '</span>';
				}
				break;
			case 'lpg_segment':
				echo esc_html( self::segment_name( RfqPostType::get( $post_id )['segment'] ) );
				break;
			case 'lpg_type':
				echo esc_html( EnquiryPostType::type_label( EnquiryPostType::get( $post_id )['type'] ) );
				break;
			case 'lpg_subject':
				echo esc_html( self::enquiry_subject( EnquiryPostType::get( $post_id ) ) );
				break;
			case 'lpg_location':
				echo esc_html( EnquiryPostType::get( $post_id )['location'] );
				break;
			case 'lpg_status':
				echo esc_html( Statuses::label( (string) get_post_status( $post_id ) ) );
				break;
		}//end switch
	}

	/**
	 * Remove Quick Edit (it offers core statuses) from record rows.
	 *
	 * @param array<string, string> $actions Row actions.
	 * @param \WP_Post              $post    Post.
	 * @return array<string, string>
	 */
	public function row_actions( $actions, $post ): array {
		$actions = is_array( $actions ) ? $actions : array();
		if ( $post instanceof \WP_Post && in_array( $post->post_type, self::types(), true ) ) {
			unset( $actions['inline hide-if-no-js'] );
			if ( isset( $actions['edit'] ) ) {
				$actions['edit'] = sprintf( '<a href="%s">%s</a>', esc_url( (string) get_edit_post_link( $post->ID ) ), esc_html__( 'View', 'lpg-commerce-core' ) );
			}
		}
		return $actions;
	}

	/**
	 * Remove Bulk Edit.
	 *
	 * @param array<string, string> $actions Bulk actions.
	 * @return array<string, string>
	 */
	public function bulk_actions( $actions ): array {
		$actions = is_array( $actions ) ? $actions : array();
		unset( $actions['edit'] );
		return $actions;
	}

	/**
	 * Segment (RFQ) or type (enquiry) filter dropdown.
	 *
	 * @param string $post_type Post type of the list.
	 */
	public function filters( $post_type ): void {
		if ( RfqPostType::POST_TYPE === $post_type ) {
			$current = self::filter_value( 'lpg_segment', self::segment_choices() );
			echo '<label class="screen-reader-text" for="lpg-filter-segment">' . esc_html__( 'Filter by segment', 'lpg-commerce-core' ) . '</label>';
			echo '<select name="lpg_segment" id="lpg-filter-segment"><option value="">' . esc_html__( 'All segments', 'lpg-commerce-core' ) . '</option>';
			foreach ( self::segment_choices() as $slug => $name ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $current, $slug, false ), esc_html( $name ) );
			}
			echo '</select>';
		} elseif ( EnquiryPostType::POST_TYPE === $post_type ) {
			$choices = array(
				EnquiryPostType::TYPE_PRODUCT  => EnquiryPostType::type_label( EnquiryPostType::TYPE_PRODUCT ),
				EnquiryPostType::TYPE_EXCHANGE => EnquiryPostType::type_label( EnquiryPostType::TYPE_EXCHANGE ),
			);
			$current = self::filter_value( 'lpg_type', $choices );
			echo '<label class="screen-reader-text" for="lpg-filter-type">' . esc_html__( 'Filter by type', 'lpg-commerce-core' ) . '</label>';
			echo '<select name="lpg_type" id="lpg-filter-type"><option value="">' . esc_html__( 'All types', 'lpg-commerce-core' ) . '</option>';
			foreach ( $choices as $slug => $name ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $current, $slug, false ), esc_html( $name ) );
			}
			echo '</select>';
		}//end if
	}

	/**
	 * Apply the segment / type filter to the admin list query.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function filter_list_query( $query ): void {
		if ( ! is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return;
		}
		$post_type = $query->get( 'post_type' );
		if ( RfqPostType::POST_TYPE === $post_type ) {
			$value = self::filter_value( 'lpg_segment', self::segment_choices() );
			$key   = RfqPostType::META_SEGMENT;
		} elseif ( EnquiryPostType::POST_TYPE === $post_type ) {
			$value = self::filter_value(
				'lpg_type',
				array(
					EnquiryPostType::TYPE_PRODUCT  => '',
					EnquiryPostType::TYPE_EXCHANGE => '',
				)
			);
			$key   = EnquiryPostType::META_TYPE;
		} else {
			return;
		}
		if ( '' === $value ) {
			return;
		}
		$meta_query   = (array) $query->get( 'meta_query' );
		$meta_query[] = array(
			'key'   => $key,
			'value' => $value,
		);
		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * A list filter value from the query string, only if it is an allowed choice.
	 *
	 * @param string                $param   Query arg.
	 * @param array<string, string> $choices Allowed values (keys).
	 */
	private static function filter_value( string $param, array $choices ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		$value = isset( $_GET[ $param ] ) ? sanitize_key( wp_unslash( $_GET[ $param ] ) ) : '';
		return isset( $choices[ $value ] ) ? $value : '';
	}

	/**
	 * Segment slug => name.
	 *
	 * @return array<string, string>
	 */
	private static function segment_choices(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => Segment::TAXONOMY,
				'hide_empty' => false,
			)
		);
		$out   = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( $term instanceof \WP_Term ) {
				$out[ $term->slug ] = html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );
			}
		}
		return $out;
	}

	/**
	 * Segment display name.
	 *
	 * @param string $slug Slug.
	 */
	private static function segment_name( string $slug ): string {
		$term = '' !== $slug ? get_term_by( 'slug', $slug, Segment::TAXONOMY ) : false;
		return $term instanceof \WP_Term ? html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) : $slug;
	}

	/**
	 * "Product name" or "Brand size" for an enquiry.
	 *
	 * @param array<string, mixed> $enquiry EnquiryPostType::get() data.
	 */
	private static function enquiry_subject( array $enquiry ): string {
		if ( EnquiryPostType::TYPE_EXCHANGE === $enquiry['type'] ) {
			return trim( $enquiry['brand'] . ' ' . $enquiry['size'] );
		}
		return $enquiry['product_id'] > 0 ? html_entity_decode( get_the_title( $enquiry['product_id'] ), ENT_QUOTES, 'UTF-8' ) : '';
	}

	/**
	 * Replace the core boxes with read-only details and a status box.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function meta_boxes( $post ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		remove_meta_box( 'submitdiv', $post->post_type, 'side' );
		remove_meta_box( 'slugdiv', $post->post_type, 'normal' );
		add_meta_box( 'lpg-record-details', __( 'Details', 'lpg-commerce-core' ), array( $this, 'render_details' ), $post->post_type, 'normal', 'high' );
		add_meta_box( 'lpg-record-status', __( 'Status', 'lpg-commerce-core' ), array( $this, 'render_status' ), $post->post_type, 'side', 'high' );
	}

	/**
	 * Read-only details box.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_details( $post ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$rows = RfqPostType::POST_TYPE === $post->post_type ? self::rfq_rows( $post->ID ) : self::enquiry_rows( $post->ID );

		echo '<table class="widefat striped lpg-record-details"><tbody>';
		foreach ( $rows as $row ) {
			// Each row's value is pre-escaped HTML built in rfq_rows()/enquiry_rows().
			echo '<tr><th scope="row" style="width:12em">' . esc_html( $row[0] ) . '</th><td>' . $row[1] . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</tbody></table>';
	}

	/**
	 * Detail rows for a quote request: [label, escaped HTML].
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array{0: string, 1: string}>
	 */
	private static function rfq_rows( int $post_id ): array {
		$rfq     = RfqPostType::get( $post_id );
		$contact = $rfq['contact'];
		$phone   = (string) preg_replace( '/\D+/', '', $contact['phone'] );

		$phone_html = '';
		if ( '' !== $phone ) {
			$reply = MessageBuilder::link(
				$phone,
				/* translators: 1: customer name, 2: record ID. */
				sprintf( __( 'Hello %1$s, regarding your quote request #%2$d:', 'lpg-commerce-core' ), $contact['name'], $post_id )
			);
			$phone_html = sprintf( '<a href="%s">+%s</a>', esc_url( 'tel:+' . $phone ), esc_html( $phone ) );
			if ( '' !== $reply ) {
				$phone_html .= sprintf( ' &middot; <a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url( $reply ), esc_html__( 'Reply on WhatsApp', 'lpg-commerce-core' ) );
			}
		}
		$email_html = is_email( $contact['email'] ) ? sprintf( '<a href="%s">%s</a>', esc_url( 'mailto:' . rawurlencode( $contact['email'] ) ), esc_html( $contact['email'] ) ) : esc_html( $contact['email'] );

		$items = '';
		foreach ( $rfq['items'] as $item ) {
			$label = esc_html( $item['description'] );
			if ( $item['product_id'] > 0 && get_post( $item['product_id'] ) ) {
				$label = sprintf( '<a href="%s">%s</a>', esc_url( (string) get_permalink( $item['product_id'] ) ), $label );
			}
			$items .= '<li>' . esc_html( number_format_i18n( $item['qty'] ) ) . ' &times; ' . $label . '</li>';
		}

		return array(
			array( __( 'Name', 'lpg-commerce-core' ), esc_html( $contact['name'] ) ),
			array( __( 'Organisation', 'lpg-commerce-core' ), esc_html( $contact['organisation'] ) ),
			array( __( 'Phone', 'lpg-commerce-core' ), $phone_html ),
			array( __( 'Email', 'lpg-commerce-core' ), $email_html ),
			array( __( 'Segment', 'lpg-commerce-core' ), esc_html( self::segment_name( $rfq['segment'] ) ) ),
			array( __( 'Items', 'lpg-commerce-core' ), '' !== $items ? '<ul style="margin:0">' . $items . '</ul>' : '' ),
			array( __( 'Requirements', 'lpg-commerce-core' ), nl2br( esc_html( $rfq['requirements'] ) ) ),
			array( __( 'Project', 'lpg-commerce-core' ), esc_html( $rfq['project'] ) ),
			array( __( 'Location', 'lpg-commerce-core' ), esc_html( $rfq['location'] ) ),
			array( __( 'Comments', 'lpg-commerce-core' ), nl2br( esc_html( $rfq['comments'] ) ) ),
			array(
				__( 'Consent', 'lpg-commerce-core' ),
				'' !== $rfq['consent']['at']
					/* translators: 1: date and time (ISO 8601), 2: privacy policy version. */
					? esc_html( sprintf( __( 'Given %1$s (privacy policy version %2$s)', 'lpg-commerce-core' ), $rfq['consent']['at'], $rfq['consent']['policy_version'] ) )
					: esc_html__( 'Not recorded', 'lpg-commerce-core' ),
			),
		);
	}

	/**
	 * Detail rows for an enquiry: [label, escaped HTML].
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array{0: string, 1: string}>
	 */
	private static function enquiry_rows( int $post_id ): array {
		$enquiry = EnquiryPostType::get( $post_id );
		$rows    = array(
			array( __( 'Type', 'lpg-commerce-core' ), esc_html( EnquiryPostType::type_label( $enquiry['type'] ) ) ),
		);
		if ( EnquiryPostType::TYPE_EXCHANGE === $enquiry['type'] ) {
			$rows[] = array( __( 'Brand', 'lpg-commerce-core' ), esc_html( $enquiry['brand'] ) );
			$rows[] = array( __( 'Size', 'lpg-commerce-core' ), esc_html( $enquiry['size'] ) );
			$rows[] = array( __( 'Request', 'lpg-commerce-core' ), esc_html( EnquiryPostType::exchange_type_label( $enquiry['exchange_type'] ) ) );
		} else {
			$product = $enquiry['product_id'] > 0 ? get_post( $enquiry['product_id'] ) : null;
			$rows[]  = array(
				__( 'Product', 'lpg-commerce-core' ),
				$product ? sprintf( '<a href="%s">%s</a>', esc_url( (string) get_permalink( $product ) ), esc_html( get_the_title( $product ) ) ) : esc_html__( '(deleted product)', 'lpg-commerce-core' ),
			);
		}
		$rows[] = array( __( 'Location', 'lpg-commerce-core' ), esc_html( $enquiry['location'] ) );
		$rows[] = array( __( 'Note', 'lpg-commerce-core' ), esc_html__( 'The visitor opened WhatsApp with this request. The message itself, and whether it was sent, is only visible in WhatsApp.', 'lpg-commerce-core' ) );
		return $rows;
	}

	/**
	 * Status box: current status and buttons for the others.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_status( $post ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		echo '<p><strong>' . esc_html( Statuses::label( $post->post_status ) ) . '</strong></p>';
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		echo '<p>';
		foreach ( self::statuses_for( $post->post_type ) as $status ) {
			if ( $status === $post->post_status ) {
				continue;
			}
			printf(
				'<a class="button" style="margin:0 4px 4px 0" href="%s">%s</a>',
				esc_url( self::status_url( $post->ID, $status ) ),
				/* translators: %s: status label. */
				esc_html( sprintf( __( 'Mark as %s', 'lpg-commerce-core' ), Statuses::label( $status ) ) )
			);
		}
		echo '</p>';
	}

	/**
	 * Nonce-protected status change URL.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $status  Target status.
	 */
	public static function status_url( int $post_id, string $status ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::STATUS_ACTION,
					'post'   => $post_id,
					'status' => $status,
				),
				admin_url( 'admin-post.php' )
			),
			self::nonce_action( $post_id )
		);
	}

	/**
	 * Nonce action for a record.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function nonce_action( int $post_id ): string {
		return self::STATUS_ACTION . '_' . $post_id;
	}

	/**
	 * Change a record's status after nonce and capability checks.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $status  Target status.
	 * @param string $nonce   Nonce.
	 * @return true|\WP_Error
	 */
	public static function change_status( int $post_id, string $status, string $nonce ) {
		if ( false === wp_verify_nonce( $nonce, self::nonce_action( $post_id ) ) ) {
			return new \WP_Error( 'lpg_bad_nonce', __( 'The link has expired. Please try again.', 'lpg-commerce-core' ) );
		}
		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, self::types(), true ) ) {
			return new \WP_Error( 'lpg_not_found', __( 'Record not found.', 'lpg-commerce-core' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'lpg_forbidden', __( 'Sorry, you are not allowed to change this record.', 'lpg-commerce-core' ) );
		}
		if ( ! in_array( $status, self::statuses_for( $post->post_type ), true ) ) {
			return new \WP_Error( 'lpg_bad_status', __( 'Unknown status.', 'lpg-commerce-core' ) );
		}
		$result = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $status,
			),
			true
		);
		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Handler (admin-post.php) for the status buttons.
	 */
	public function handle_status_request(): void {
		// Nonce verified in change_status().
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$result = self::change_status( $post_id, $status, $nonce );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 403 ) );
		}
		wp_safe_redirect( add_query_arg( 'lpg_status_updated', '1', admin_url( 'post.php?post=' . $post_id . '&action=edit' ) ) );
		exit;
	}

	/**
	 * Confirmation notice after a status change.
	 */
	public function status_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display flag only.
		if ( empty( $_GET['lpg_status_updated'] ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, self::types(), true ) ) {
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Status updated.', 'lpg-commerce-core' ) . '</p></div>';
	}
}
