<?php
/**
 * REST API hardening.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Security;

use LPG\Commerce\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Blocks anonymous user enumeration through the core users endpoints (§13.10,
 * security L1): `/wp/v2/users` and `/wp/v2/users/<id>` return 401
 * `rest_forbidden` to logged-out visitors. Logged-in users keep core
 * behaviour (so anyone with `list_users` is unaffected) and every other route
 * is untouched.
 */
final class RestHardening implements Service {

	/**
	 * Routes blocked for anonymous requests (matched case-insensitively, as
	 * the REST server matches routes).
	 */
	private const BLOCKED_ROUTE = '#^/wp/v2/users(?:/\d+)?/?$#i';

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_filter( 'rest_pre_dispatch', array( $this, 'block_anonymous_users' ), 10, 3 );
	}

	/**
	 * Short-circuit anonymous requests to the users endpoints.
	 *
	 * @param mixed            $result  Response to replace the requested one, or null.
	 * @param \WP_REST_Server  $server  Server instance.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function block_anonymous_users( $result, $server, $request ) {
		unset( $server );
		if ( null !== $result || ! $request instanceof \WP_REST_Request || is_user_logged_in() ) {
			return $result;
		}
		if ( ! preg_match( self::BLOCKED_ROUTE, (string) $request->get_route() ) ) {
			return $result;
		}
		return new \WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to do that.', 'lpg-commerce-core' ),
			array( 'status' => 401 )
		);
	}
}
