<?php
/**
 * Shared behaviour for the public form endpoints.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Rest;

use LPG\Commerce\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Base for `lpg/v1/rfq` and `lpg/v1/enquiry` (§13.4).
 *
 * Request handling order, all inside the callback so it is fully controlled:
 * 1. REST nonce (`wp_rest`) check, also for logged-out visitors;
 * 2. rate limit, per IP and route (counts every request that passed 1);
 * 3. honeypot: a filled `hp` gets a fake 201 and nothing is stored;
 * 4. schema validation and sanitisation of each argument, then the
 *    controller's own rules (required fields, cross-field checks).
 *
 * The route `args` carry the full JSON schema (exposed by OPTIONS) but no
 * validate/sanitize callbacks, so WordPress does not reject a request before
 * steps 1–3 have run. Step 4 applies the same schema.
 */
abstract class FormController implements Service {

	public const NAMESPACE = 'lpg/v1';

	public const RATE_LIMIT = 5;

	public const RATE_WINDOW = 600;

	/**
	 * Route path, e.g. '/rfq'.
	 */
	abstract protected function route(): string;

	/**
	 * Argument schema, name => JSON schema (without `required`).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	abstract protected function schema(): array;

	/**
	 * Handle a request that passed steps 1–3 and schema validation.
	 *
	 * @param array<string, mixed> $params Sanitised params (only those sent).
	 * @return \WP_REST_Response|\WP_Error
	 */
	abstract protected function handle( array $params );

	/**
	 * Fake success body for a filled honeypot.
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function honeypot_response(): array;

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the route.
	 */
	public function register_routes(): void {
		$args = array();
		foreach ( $this->schema() as $name => $schema ) {
			// No validate/sanitize callbacks here: see the class comment.
			$args[ $name ] = array_merge( $schema, array( 'sanitize_callback' => null ) );
		}

		register_rest_route(
			self::NAMESPACE,
			$this->route(),
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'dispatch' ),
				// Public by design: anonymous visitors submit these forms. Abuse is
				// limited inside the callback by the REST nonce, the per-IP rate
				// limit and the honeypot, and nothing is ever returned but the
				// visitor's own new record ID.
				'permission_callback' => '__return_true',
				'args'                => $args,
			)
		);
	}

	/**
	 * REST callback.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function dispatch( \WP_REST_Request $request ) {
		if ( ! $this->nonce_is_valid( $request ) ) {
			return new \WP_Error(
				'rest_cookie_invalid_nonce',
				__( 'Your session has expired. Please reload the page and try again.', 'lpg-commerce-core' ),
				array( 'status' => 403 )
			);
		}

		$retry_after = $this->hit_rate_limit();
		if ( $retry_after > 0 ) {
			$response = new \WP_REST_Response(
				array(
					'code'    => 'lpg_rate_limited',
					'message' => __( 'Too many requests. Please wait a few minutes and try again, or contact us by phone.', 'lpg-commerce-core' ),
					'data'    => array( 'status' => 429 ),
				),
				429
			);
			$response->header( 'Retry-After', (string) $retry_after );
			return $response;
		}

		$hp = $request->get_param( 'hp' );
		if ( null !== $hp && '' !== $hp ) {
			return new \WP_REST_Response( $this->honeypot_response(), 201 );
		}

		$params = array();
		$errors = array();
		foreach ( $this->schema() as $name => $schema ) {
			$value = $request->get_param( $name );
			if ( null === $value ) {
				continue;
			}
			$valid = rest_validate_value_from_schema( $value, $schema, $name );
			if ( is_wp_error( $valid ) ) {
				$errors[ $name ] = $valid->get_error_message();
				continue;
			}
			$params[ $name ] = rest_sanitize_value_from_schema( $value, $schema, $name );
		}
		if ( $errors ) {
			return self::invalid( $errors );
		}

		return $this->handle( $params );
	}

	/**
	 * Build the `rest_invalid_param` error.
	 *
	 * @param array<string, string> $errors Param => message.
	 */
	protected static function invalid( array $errors ): \WP_Error {
		return new \WP_Error(
			'rest_invalid_param',
			/* translators: %s: list of field names. */
			sprintf( __( 'Please check: %s.', 'lpg-commerce-core' ), implode( ', ', array_keys( $errors ) ) ),
			array(
				'status' => 400,
				'params' => $errors,
			)
		);
	}

	/**
	 * Verify the `wp_rest` nonce from `X-WP-Nonce` or `_wpnonce`.
	 *
	 * Core's cookie check only rejects a *bad* nonce; a missing one just means
	 * "anonymous". For logged-out visitors the nonce is tied to user 0 and is
	 * still a time-limited token printed into the page, so it is required.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	private function nonce_is_valid( \WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( null === $nonce || '' === $nonce ) {
			$nonce = $request->get_param( '_wpnonce' );
		}
		return is_string( $nonce ) && '' !== $nonce && false !== wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Count this request against the per-IP, per-route limit.
	 *
	 * Fixed window of RATE_WINDOW seconds stored in a transient keyed by a
	 * salted hash of REMOTE_ADDR (proxy headers are not trusted, so they
	 * cannot be spoofed to dodge the limit).
	 *
	 * @return int Seconds to wait when the limit is reached, otherwise 0.
	 */
	private function hit_rate_limit(): int {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'lpg_rl_' . substr( hash_hmac( 'sha256', $this->route() . '|' . $ip, wp_salt( 'nonce' ) ), 0, 32 );
		$now = time();

		$entry = get_transient( $key );
		if ( ! is_array( $entry ) || ! isset( $entry['start'], $entry['count'] ) || $now - (int) $entry['start'] >= self::RATE_WINDOW ) {
			$entry = array(
				'start' => $now,
				'count' => 0,
			);
		}

		$remaining = self::RATE_WINDOW - ( $now - (int) $entry['start'] );
		if ( (int) $entry['count'] >= self::RATE_LIMIT ) {
			return max( 1, $remaining );
		}

		$entry['count'] = (int) $entry['count'] + 1;
		set_transient( $key, $entry, max( 1, $remaining ) );
		return 0;
	}

	/**
	 * Whether a product ID is a published product.
	 *
	 * @param int $product_id Product ID.
	 */
	protected static function is_published_product( int $product_id ): bool {
		return $product_id > 0 && 'product' === get_post_type( $product_id ) && 'publish' === get_post_status( $product_id );
	}

	/**
	 * Normalise a Kenyan phone number to 254XXXXXXXXX, or '' when invalid.
	 * Accepts 07XX…, 01XX…, landlines like 020…, with or without +254 / 254,
	 * spaces, dashes, dots and brackets.
	 *
	 * @param string $phone Raw phone.
	 */
	public static function normalise_ke_phone( string $phone ): string {
		$digits = (string) preg_replace( '/[\s\-().]+/', '', $phone );
		if ( preg_match( '/^(?:\+?254|0)([1-9]\d{8})$/', $digits, $m ) ) {
			return '254' . $m[1];
		}
		return '';
	}

	/**
	 * Length in characters.
	 *
	 * @param string $value Value.
	 */
	protected static function length( string $value ): int {
		return mb_strlen( $value, 'UTF-8' );
	}
}
