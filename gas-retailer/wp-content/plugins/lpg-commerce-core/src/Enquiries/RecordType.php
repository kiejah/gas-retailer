<?php
/**
 * Shared behaviour for the private record post types.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Enquiries;

use LPG\Commerce\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Base for `lpg_rfq` and `lpg_enquiry` (§13.3): private CPTs with their own
 * capability type, no editor and no manual creation. Records are created
 * only through the REST controllers.
 */
abstract class RecordType implements Service {

	/**
	 * Roles that receive the record capabilities (activation / first install only, L4).
	 */
	public const ROLES = array( 'administrator', 'shop_manager' );

	/**
	 * Post type slug.
	 */
	abstract public static function post_type(): string;

	/**
	 * Capability type, singular and plural.
	 *
	 * @return array{0: string, 1: string}
	 */
	abstract protected static function capability_type(): array;

	/**
	 * Statuses this type uses, in workflow order.
	 *
	 * @return string[]
	 */
	abstract public static function statuses(): array;

	/**
	 * Labels for register_post_type().
	 *
	 * @return array<string, string>
	 */
	abstract protected static function labels(): array;

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'init', array( static::class, 'register_post_type' ) );
	}

	/**
	 * Register the post type and the shared statuses. Runs on `init`.
	 */
	public static function register_post_type(): void {
		Statuses::register();
		register_post_type(
			static::post_type(),
			array(
				'labels'              => static::labels(),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => 'woocommerce',
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => false,
				'delete_with_user'    => false,
				'hierarchical'        => false,
				'supports'            => false,
				'capability_type'     => static::capability_type(),
				'map_meta_cap'        => true,
				'capabilities'        => array(
					// Records come from the public forms only.
					'create_posts' => 'do_not_allow',
				),
			)
		);
	}

	/**
	 * Primitive capabilities for this type.
	 *
	 * @return string[]
	 */
	public static function caps(): array {
		$plural = static::capability_type()[1];
		return array(
			"edit_{$plural}",
			"edit_others_{$plural}",
			"edit_private_{$plural}",
			"edit_published_{$plural}",
			"publish_{$plural}",
			"read_private_{$plural}",
			"delete_{$plural}",
			"delete_others_{$plural}",
			"delete_private_{$plural}",
			"delete_published_{$plural}",
		);
	}

	/**
	 * Grant the capabilities to ROLES.
	 */
	public static function add_caps(): void {
		foreach ( self::ROLES as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( static::caps() as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Whether a post is a record of this type.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function is_record( int $post_id ): bool {
		return $post_id > 0 && get_post_type( $post_id ) === static::post_type();
	}

	/**
	 * Insert a record with meta, then give it its generated title.
	 *
	 * @param array<string, mixed> $meta        Meta key => value (already sanitised).
	 * @param callable             $title_maker fn( int $id ): string.
	 * @return int|\WP_Error Post ID.
	 */
	protected static function insert( array $meta, callable $title_maker ) {
		$id = wp_insert_post(
			array(
				'post_type'   => static::post_type(),
				'post_status' => Statuses::NEW,
				'post_title'  => '',
				'post_author' => 0,
				'meta_input'  => $meta,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		wp_update_post(
			array(
				'ID'         => (int) $id,
				'post_title' => wp_strip_all_tags( $title_maker( (int) $id ) ),
				'post_name'  => static::post_type() . '-' . $id,
			)
		);
		return (int) $id;
	}
}
