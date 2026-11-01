<?php
/**
 * Custom post statuses for quote requests and enquiries.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Enquiries;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the §13.3 statuses. Post statuses are global in WordPress, so
 * both record types share one registration (`lpg-new` is used by both).
 */
final class Statuses {

	public const NEW     = 'lpg-new';
	public const REVIEW  = 'lpg-review';
	public const QUOTED  = 'lpg-quoted';
	public const CLOSED  = 'lpg-closed';
	public const HANDLED = 'lpg-handled';

	/**
	 * Register every status once. Runs on `init` (called by both post types).
	 */
	public static function register(): void {
		foreach ( self::labels() as $status => $label ) {
			if ( get_post_status_object( $status ) ) {
				continue;
			}
			register_post_status(
				$status,
				array(
					'label'                     => $label[0],
					'label_count'               => $label[1],
					'public'                    => false,
					'internal'                  => false,
					'protected'                 => true,
					'private'                   => false,
					'exclude_from_search'       => true,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
				)
			);
		}
	}

	/**
	 * Translated label for a status, or the slug when unknown.
	 *
	 * @param string $status Status slug.
	 */
	public static function label( string $status ): string {
		$labels = self::labels();
		return isset( $labels[ $status ] ) ? $labels[ $status ][0] : $status;
	}

	/**
	 * Labels and count labels.
	 *
	 * @return array<string, array{0: string, 1: array<int|string, mixed>}>
	 */
	private static function labels(): array {
		return array(
			/* translators: Record status. */
			self::NEW     => array( _x( 'New', 'record status', 'lpg-commerce-core' ), _n_noop( 'New <span class="count">(%s)</span>', 'New <span class="count">(%s)</span>', 'lpg-commerce-core' ) ),
			/* translators: Record status. */
			self::REVIEW  => array( _x( 'In review', 'record status', 'lpg-commerce-core' ), _n_noop( 'In review <span class="count">(%s)</span>', 'In review <span class="count">(%s)</span>', 'lpg-commerce-core' ) ),
			/* translators: Record status. */
			self::QUOTED  => array( _x( 'Quoted', 'record status', 'lpg-commerce-core' ), _n_noop( 'Quoted <span class="count">(%s)</span>', 'Quoted <span class="count">(%s)</span>', 'lpg-commerce-core' ) ),
			/* translators: Record status. */
			self::CLOSED  => array( _x( 'Closed', 'record status', 'lpg-commerce-core' ), _n_noop( 'Closed <span class="count">(%s)</span>', 'Closed <span class="count">(%s)</span>', 'lpg-commerce-core' ) ),
			/* translators: Record status. */
			self::HANDLED => array( _x( 'Handled', 'record status', 'lpg-commerce-core' ), _n_noop( 'Handled <span class="count">(%s)</span>', 'Handled <span class="count">(%s)</span>', 'lpg-commerce-core' ) ),
		);
	}
}
