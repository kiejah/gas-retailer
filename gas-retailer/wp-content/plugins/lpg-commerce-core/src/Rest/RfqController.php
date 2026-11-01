<?php
/**
 * POST lpg/v1/rfq: quote requests.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Rest;

use LPG\Commerce\Enquiries\RfqPostType;
use LPG\Commerce\Settings\Settings;
use LPG\Commerce\Taxonomies\Segment;

defined( 'ABSPATH' ) || exit;

/**
 * Creates `lpg_rfq` records from the RFQ form (§13.3, §13.4).
 *
 * Body (JSON or form):
 * - contact: { name (required, 2–100), organisation (≤150), phone (required,
 *   Kenyan), email (required) }
 * - segment (required): an `lpg_segment` term slug
 * - items (required, 1–50): [ { product_id? (published product),
 *   description (≤500; required unless product_id is given), qty (1–10 000) } ]
 * - requirements (≤2000), project (≤200), location (≤200), comments (≤2000)
 * - consent: true (required)
 * - hp: honeypot, must be empty
 *
 * Returns 201 { id, message }.
 */
final class RfqController extends FormController {

	public const MAX_ITEMS = 50;

	/**
	 * Route path.
	 */
	protected function route(): string {
		return '/rfq';
	}

	/**
	 * Argument schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function schema(): array {
		return array(
			'contact'      => array(
				'description'          => __( 'Contact details.', 'lpg-commerce-core' ),
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'name'         => array(
						'title'     => __( 'Name', 'lpg-commerce-core' ),
						'type'      => 'string',
						'minLength' => 2,
						'maxLength' => 100,
					),
					'organisation' => array(
						'title'     => __( 'Organisation', 'lpg-commerce-core' ),
						'type'      => 'string',
						'maxLength' => 150,
					),
					'phone'        => array(
						'title'     => __( 'Phone', 'lpg-commerce-core' ),
						'type'      => 'string',
						'maxLength' => 30,
					),
					'email'        => array(
						'title'     => __( 'Email', 'lpg-commerce-core' ),
						'type'      => 'string',
						'format'    => 'email',
						'maxLength' => 254,
					),
				),
			),
			'segment'      => array(
				'title'       => __( 'Segment', 'lpg-commerce-core' ),
				'description' => __( 'Customer segment slug.', 'lpg-commerce-core' ),
				'type'        => 'string',
				'maxLength'   => 50,
			),
			'items'        => array(
				'title'       => __( 'Items', 'lpg-commerce-core' ),
				'description' => __( 'Requested items.', 'lpg-commerce-core' ),
				'type'        => 'array',
				'minItems'    => 1,
				'maxItems'    => self::MAX_ITEMS,
				'items'       => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'product_id'  => array(
							'title'   => __( 'Product', 'lpg-commerce-core' ),
							'type'    => 'integer',
							'minimum' => 0,
						),
						'description' => array(
							'title'     => __( 'Item description', 'lpg-commerce-core' ),
							'type'      => 'string',
							'maxLength' => 500,
						),
						'qty'         => array(
							'title'   => __( 'Quantity', 'lpg-commerce-core' ),
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 10000,
						),
					),
				),
			),
			'requirements' => array(
				'title'     => __( 'Requirements', 'lpg-commerce-core' ),
				'type'      => 'string',
				'maxLength' => 2000,
			),
			'project'      => array(
				'title'     => __( 'Project', 'lpg-commerce-core' ),
				'type'      => 'string',
				'maxLength' => 200,
			),
			'location'     => array(
				'title'     => __( 'Location', 'lpg-commerce-core' ),
				'type'      => 'string',
				'maxLength' => 200,
			),
			'comments'     => array(
				'title'     => __( 'Comments', 'lpg-commerce-core' ),
				'type'      => 'string',
				'maxLength' => 2000,
			),
			'consent'      => array(
				'title'       => __( 'Consent', 'lpg-commerce-core' ),
				'description' => __( 'Consent to the privacy notice. Must be true.', 'lpg-commerce-core' ),
				'type'        => 'boolean',
			),
			'hp'           => array(
				'description' => __( 'Leave empty.', 'lpg-commerce-core' ),
				'type'        => 'string',
			),
		);
	}

	/**
	 * Fake success for the honeypot.
	 *
	 * @return array<string, mixed>
	 */
	protected function honeypot_response(): array {
		return array(
			'id'      => 0,
			'message' => self::success_message(),
		);
	}

	/**
	 * Validate the business rules and create the record.
	 *
	 * @param array<string, mixed> $params Schema-valid params.
	 * @return \WP_REST_Response|\WP_Error
	 */
	protected function handle( array $params ) {
		$errors  = array();
		$contact = isset( $params['contact'] ) && is_array( $params['contact'] ) ? $params['contact'] : array();

		$name = sanitize_text_field( (string) ( $contact['name'] ?? '' ) );
		if ( self::length( $name ) < 2 ) {
			$errors['contact.name'] = __( 'Enter your name.', 'lpg-commerce-core' );
		}
		$organisation = sanitize_text_field( (string) ( $contact['organisation'] ?? '' ) );
		$phone        = self::normalise_ke_phone( (string) ( $contact['phone'] ?? '' ) );
		if ( '' === $phone ) {
			$errors['contact.phone'] = __( 'Enter a Kenyan phone number, for example 07XX XXX XXX.', 'lpg-commerce-core' );
		}
		$email = sanitize_email( (string) ( $contact['email'] ?? '' ) );
		if ( '' === $email || ! is_email( $email ) ) {
			$errors['contact.email'] = __( 'Enter a valid email address.', 'lpg-commerce-core' );
		}

		$segment = sanitize_key( (string) ( $params['segment'] ?? '' ) );
		if ( '' === $segment || ! term_exists( $segment, Segment::TAXONOMY ) ) {
			$errors['segment'] = __( 'Choose who the quote is for.', 'lpg-commerce-core' );
		}

		$items = array();
		foreach ( (array) ( $params['items'] ?? array() ) as $index => $item ) {
			$item        = is_array( $item ) ? $item : array();
			$product_id  = (int) ( $item['product_id'] ?? 0 );
			$description = sanitize_text_field( (string) ( $item['description'] ?? '' ) );
			$qty         = (int) ( $item['qty'] ?? 0 );

			if ( $product_id > 0 && ! self::is_published_product( $product_id ) ) {
				$errors[ "items.{$index}.product_id" ] = __( 'This product is not available.', 'lpg-commerce-core' );
			}
			if ( '' === $description && $product_id > 0 && self::is_published_product( $product_id ) ) {
				$description = html_entity_decode( get_the_title( $product_id ), ENT_QUOTES, 'UTF-8' );
			}
			if ( '' === $description ) {
				$errors[ "items.{$index}.description" ] = __( 'Describe the item you need.', 'lpg-commerce-core' );
			}
			if ( $qty < 1 || $qty > 10000 ) {
				$errors[ "items.{$index}.qty" ] = __( 'Enter a quantity from 1 to 10,000.', 'lpg-commerce-core' );
			}
			$items[] = array(
				'product_id'  => $product_id,
				'description' => $description,
				'qty'         => $qty,
			);
		}//end foreach
		if ( ! $items ) {
			$errors['items'] = __( 'Add at least one item.', 'lpg-commerce-core' );
		}

		if ( true !== ( $params['consent'] ?? null ) ) {
			$errors['consent'] = __( 'Please agree to the privacy notice so we can respond to your request.', 'lpg-commerce-core' );
		}

		if ( $errors ) {
			return self::invalid( $errors );
		}

		$id = RfqPostType::create(
			array(
				'contact'      => array(
					'name'         => $name,
					'organisation' => $organisation,
					'phone'        => $phone,
					'email'        => $email,
				),
				'segment'      => $segment,
				'items'        => $items,
				'requirements' => sanitize_textarea_field( (string) ( $params['requirements'] ?? '' ) ),
				'project'      => sanitize_text_field( (string) ( $params['project'] ?? '' ) ),
				'location'     => sanitize_text_field( (string) ( $params['location'] ?? '' ) ),
				'comments'     => sanitize_textarea_field( (string) ( $params['comments'] ?? '' ) ),
				'consent'      => array(
					'at'             => gmdate( 'c' ),
					'policy_version' => (string) Settings::get( 'privacy_policy_version' ),
				),
			)
		);
		if ( is_wp_error( $id ) ) {
			return new \WP_Error( 'lpg_save_failed', __( 'Sorry, your request could not be saved. Please contact us by phone.', 'lpg-commerce-core' ), array( 'status' => 500 ) );
		}

		return new \WP_REST_Response(
			array(
				'id'      => $id,
				'message' => self::success_message(),
			),
			201
		);
	}

	/**
	 * Success message.
	 */
	private static function success_message(): string {
		return __( 'Thank you. We have received your quote request and will contact you soon.', 'lpg-commerce-core' );
	}
}
