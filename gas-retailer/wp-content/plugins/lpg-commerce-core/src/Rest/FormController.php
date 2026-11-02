<?php
/**
 * Shared behaviour for the public form endpoints.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Rest;

use LPG\Commerce\Enquiries\Notifications;
use LPG\Commerce\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Base for `lpg/v1/rfq` and `lpg/v1/enquiry` (§13.4).
 *
 * Request handling order, all inside the callback so it is fully controlled:
 * 1. REST nonce (`wp_rest`) check, also for logged-out visitors;
 * 2. honeypot: a filled `hp` gets a fake 201, nothing is stored or counted;
 * 3. per-IP limits (ClientIp bucket, see C22): RATE_LIMIT accepted
 *    submissions and INVALID_LIMIT invalid bodies per RATE_WINDOW, counted
 *    separately after the outcome is known;
 * 4. schema validation and sanitisation of each argument, then the
 *    controller's own rules (required fields, cross-field checks);
 * 5. the site-wide hourly ceiling (save_with_ceiling()).
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
	 * Invalid (400) requests allowed per IP per RATE_WINDOW.
	 */
	public const INVALID_LIMIT = 20;

	/**
	 * Default site-wide submissions per rolling hour (see global_limit()).
	 */
	public const GLOBAL_LIMIT = 30;

	/**
	 * The request being handled (raw values, before schema sanitising).
	 *
	 * @var \WP_REST_Request|null
	 */
	protected ?\WP_REST_Request $request = null;

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

		// Honeypot first: bot hits never touch a visitor's rate-limit budget.
		$hp = $request->get_param( 'hp' );
		if ( null !== $hp && '' !== $hp ) {
			return new \WP_REST_Response( $this->honeypot_response(), 201 );
		}

		$client      = ClientIp::bucket( ClientIp::current() );
		$retry_after = $this->rate_limited( $client );
		if ( $retry_after > 0 ) {
			return self::too_many( $retry_after );
		}

		$response = $this->validate_and_handle( $request );

		// Count the outcome: accepted submissions and invalid bodies have
		// separate per-IP budgets, so junk cannot use up a visitor's five.
		$status = $response instanceof \WP_Error ? (int) ( $response->get_error_data()['status'] ?? 400 ) : $response->get_status();
		if ( 201 === $status ) {
			RateCounter::incr( $this->ip_key( 'v', $client, self::RATE_WINDOW ), self::window_left( self::RATE_WINDOW ) );
		} elseif ( 400 === $status ) {
			RateCounter::incr( $this->ip_key( 'i', $client, self::RATE_WINDOW ), self::window_left( self::RATE_WINDOW ) );
		}
		return $response;
	}

	/**
	 * Schema validation, then the controller's own rules.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function validate_and_handle( \WP_REST_Request $request ) {
		$this->request = $request;
		$params        = array();
		$errors        = array();
		foreach ( $this->schema() as $name => $schema ) {
			$value = $request->get_param( $name );
			if ( null === $value ) {
				continue;
			}
			$field_errors = self::validate_field( $value, $schema, $name );
			if ( $field_errors ) {
				$errors = array_merge( $errors, $field_errors );
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
	 * Validate one param against its schema, reporting errors at the most
	 * specific key so a form can mark the exact field: `contact.email` for an
	 * object property, `items.2.qty` for a property of an array item, the
	 * param name itself otherwise.
	 *
	 * @param mixed                $value  Value.
	 * @param array<string, mixed> $schema Schema.
	 * @param string               $name   Param name / key prefix.
	 * @return array<string, string> Key => message.
	 */
	private static function validate_field( $value, array $schema, string $name ): array {
		$type = $schema['type'] ?? '';

		if ( 'object' === $type && ! empty( $schema['properties'] ) && is_array( $value ) ) {
			$errors = array();
			foreach ( $value as $key => $item ) {
				if ( ! isset( $schema['properties'][ $key ] ) ) {
					if ( false === ( $schema['additionalProperties'] ?? true ) ) {
						$errors[ $name ] = __( 'Unexpected field.', 'lpg-commerce-core' );
					}
					continue;
				}
				$errors = array_merge( $errors, self::validate_field( $item, $schema['properties'][ $key ], $name . '.' . $key ) );
			}
			return $errors;
		}

		if ( 'array' === $type && isset( $schema['items'] ) && is_array( $value ) && wp_is_numeric_array( $value ) ) {
			$outer = $schema;
			unset( $outer['items'] );
			$valid = rest_validate_value_from_schema( $value, $outer, (string) ( $schema['title'] ?? $name ) );
			if ( is_wp_error( $valid ) ) {
				return array( $name => $valid->get_error_message() );
			}
			$errors = array();
			foreach ( array_values( $value ) as $index => $item ) {
				$errors = array_merge( $errors, self::validate_field( $item, $schema['items'], $name . '.' . $index ) );
			}
			return $errors;
		}

		// The schema `title` (translated) names the field in core's messages.
		$valid = rest_validate_value_from_schema( $value, $schema, (string) ( $schema['title'] ?? $name ) );
		return is_wp_error( $valid ) ? array( $name => $valid->get_error_message() ) : array();
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
	 * Seconds to wait if this client is over either per-IP budget, else 0.
	 *
	 * @param string $client Client bucket (ClientIp::bucket()).
	 */
	private function rate_limited( string $client ): int {
		$over = RateCounter::get( $this->ip_key( 'v', $client, self::RATE_WINDOW ) ) >= self::RATE_LIMIT
			|| RateCounter::get( $this->ip_key( 'i', $client, self::RATE_WINDOW ) ) >= self::INVALID_LIMIT;
		return $over ? self::window_left( self::RATE_WINDOW ) : 0;
	}

	/**
	 * The 429 response.
	 *
	 * @param int $retry_after Seconds.
	 */
	private static function too_many( int $retry_after ): \WP_REST_Response {
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

	/**
	 * Counter key for a per-IP bucket in the current fixed window.
	 *
	 * @param string $kind   v (accepted) or i (invalid).
	 * @param string $client Client bucket.
	 * @param int    $window Window length in seconds.
	 */
	private function ip_key( string $kind, string $client, int $window ): string {
		return RateCounter::key( 'lpg_rl_' . $kind . '_', $this->route(), $client, (string) intdiv( time(), $window ) );
	}

	/**
	 * Seconds left in the current fixed window.
	 *
	 * @param int $window Window length in seconds.
	 */
	private static function window_left( int $window ): int {
		return max( 1, $window - ( time() % $window ) );
	}

	/**
	 * Site-wide submissions per rolling hour above which notifications are
	 * suppressed for this route (records are still saved).
	 */
	protected function global_limit(): int {
		/**
		 * Filters the site-wide hourly submission ceiling for an lpg/v1 route.
		 * Above it records are still saved, but notification emails are
		 * replaced by one "unusual volume" alert per hour.
		 *
		 * @param int    $limit Submissions per rolling hour.
		 * @param string $route Route without the namespace, e.g. 'rfq'.
		 */
		return max( 1, (int) apply_filters( 'lpg_global_rate_limit', static::GLOBAL_LIMIT, ltrim( $this->route(), '/' ) ) );
	}

	/**
	 * Save a record under the site-wide ceiling.
	 *
	 * Counts the submission in an hourly bucket and estimates the rolling-hour
	 * total as this hour plus the overlapping share of the previous hour.
	 * Above the ceiling the record is still created (genuine leads are never
	 * dropped) but its notification is suppressed, and Sales gets a single
	 * "unusual volume" alert for the hour.
	 *
	 * @param callable $create fn(): int|\WP_Error.
	 * @return int|\WP_Error Post ID.
	 */
	protected function save_with_ceiling( callable $create ) {
		$route = ltrim( $this->route(), '/' );
		$hour  = intdiv( time(), HOUR_IN_SECONDS );
		$now   = RateCounter::incr( RateCounter::key( 'lpg_rl_g_', $route, (string) $hour ), 2 * HOUR_IN_SECONDS );
		$prev  = RateCounter::get( RateCounter::key( 'lpg_rl_g_', $route, (string) ( $hour - 1 ) ) );
		$share = 1 - ( time() % HOUR_IN_SECONDS ) / HOUR_IN_SECONDS;
		$count = (int) ceil( $now + $prev * $share );
		$limit = $this->global_limit();

		if ( $count <= $limit ) {
			return $create();
		}

		$id = Notifications::suppressed( $create );
		if ( RateCounter::add_once( RateCounter::key( 'lpg_rl_a_', $route, (string) $hour ), HOUR_IN_SECONDS ) ) {
			Notifications::send_volume_alert( $route, $count, $limit );
		}
		return $id;
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
	 * Whether a product can be enquired about: published, no post password,
	 * and visible in the catalogue (whether or not it is enquiry only).
	 *
	 * @param int $product_id Product ID.
	 */
	protected static function is_enquirable_product( int $product_id ): bool {
		if ( ! self::is_published_product( $product_id ) || '' !== (string) get_post_field( 'post_password', $product_id ) ) {
			return false;
		}
		$product = wc_get_product( $product_id );
		return $product instanceof \WC_Product && $product->is_visible();
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
