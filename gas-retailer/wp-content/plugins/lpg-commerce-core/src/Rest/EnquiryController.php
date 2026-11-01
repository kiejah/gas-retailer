<?php
/**
 * POST lpg/v1/enquiry: logged WhatsApp enquiries.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Rest;

use LPG\Commerce\Enquiries\EnquiryPostType;
use LPG\Commerce\Settings\Settings;
use LPG\Commerce\Taxonomies\CylinderBrands;
use LPG\Commerce\WhatsApp\MessageBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Logs a WhatsApp enquiry the visitor is about to open and returns the
 * click-to-chat link (§13.3, §13.4, §13.5).
 *
 * Body (JSON or form):
 * - type (required): product | exchange
 * - product: product_id (required, published product), location (optional)
 * - exchange: brand (required, cylinder brand slug), size (required, one of
 *   that brand's sizes), exchange_type (required: exchange | new),
 *   location (required, ≤200)
 * - hp: honeypot, must be empty
 *
 * Returns 201 { id, whatsapp_url }. `whatsapp_url` is '' when no WhatsApp
 * number is configured, so the block can show its fallback.
 */
final class EnquiryController extends FormController {

	/**
	 * Route path.
	 */
	protected function route(): string {
		return '/enquiry';
	}

	/**
	 * Argument schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function schema(): array {
		return array(
			'type'          => array(
				'title'       => __( 'Enquiry type', 'lpg-commerce-core' ),
				'description' => __( 'Enquiry type.', 'lpg-commerce-core' ),
				'type'        => 'string',
				'enum'        => array( EnquiryPostType::TYPE_PRODUCT, EnquiryPostType::TYPE_EXCHANGE ),
			),
			'product_id'    => array(
				'title'   => __( 'Product', 'lpg-commerce-core' ),
				'type'    => 'integer',
				'minimum' => 1,
			),
			'brand'         => array(
				'title'       => __( 'Brand', 'lpg-commerce-core' ),
				'description' => __( 'Cylinder brand slug.', 'lpg-commerce-core' ),
				'type'        => 'string',
				'maxLength'   => 200,
			),
			'size'          => array(
				'title'     => __( 'Size', 'lpg-commerce-core' ),
				'type'      => 'string',
				'maxLength' => 20,
			),
			'exchange_type' => array(
				'title' => __( 'Exchange or new', 'lpg-commerce-core' ),
				'type'  => 'string',
				'enum'  => EnquiryPostType::EXCHANGE_TYPES,
			),
			'location'      => array(
				'title'     => __( 'Delivery location', 'lpg-commerce-core' ),
				'type'      => 'string',
				'maxLength' => 200,
			),
			'hp'            => array(
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
			'id'           => 0,
			'whatsapp_url' => '',
		);
	}

	/**
	 * Validate the business rules, log the enquiry and build the link.
	 *
	 * @param array<string, mixed> $params Schema-valid params.
	 * @return \WP_REST_Response|\WP_Error
	 */
	protected function handle( array $params ) {
		$type     = (string) ( $params['type'] ?? '' );
		$location = sanitize_text_field( (string) ( $params['location'] ?? '' ) );
		$errors   = array();

		if ( EnquiryPostType::TYPE_PRODUCT === $type ) {
			$product_id = (int) ( $params['product_id'] ?? 0 );
			if ( ! self::is_published_product( $product_id ) ) {
				$errors['product_id'] = __( 'This product is not available.', 'lpg-commerce-core' );
			}
			if ( $errors ) {
				return self::invalid( $errors );
			}
			$data    = array(
				'type'       => $type,
				'product_id' => $product_id,
				'location'   => $location,
			);
			$product = wc_get_product( $product_id );
			$message = MessageBuilder::build(
				(string) Settings::get( 'wa_template_product' ),
				array(
					'product'  => $product ? $product->get_name() : get_the_title( $product_id ),
					'sku'      => $product ? $product->get_sku() : '',
					'url'      => get_permalink( $product_id ),
					'location' => $location,
				)
			);
		} elseif ( EnquiryPostType::TYPE_EXCHANGE === $type ) {
			$brand = null;
			$slug  = sanitize_title( (string) ( $params['brand'] ?? '' ) );
			foreach ( CylinderBrands::all() as $candidate ) {
				if ( '' !== $slug && $candidate['slug'] === $slug ) {
					$brand = $candidate;
					break;
				}
			}
			$size = trim( sanitize_text_field( (string) ( $params['size'] ?? '' ) ) );
			if ( null === $brand ) {
				$errors['brand'] = __( 'Choose a cylinder brand.', 'lpg-commerce-core' );
			} elseif ( ! in_array( $size, $brand['sizes'], true ) ) {
				$errors['size'] = __( 'Choose a size this brand offers.', 'lpg-commerce-core' );
			}
			$exchange_type = (string) ( $params['exchange_type'] ?? '' );
			if ( ! in_array( $exchange_type, EnquiryPostType::EXCHANGE_TYPES, true ) ) {
				$errors['exchange_type'] = __( 'Choose exchange or new cylinder.', 'lpg-commerce-core' );
			}
			if ( '' === $location ) {
				$errors['location'] = __( 'Enter your delivery location.', 'lpg-commerce-core' );
			}
			if ( $errors ) {
				return self::invalid( $errors );
			}
			$data    = array(
				'type'          => $type,
				'brand'         => $brand['name'],
				'size'          => $size,
				'exchange_type' => $exchange_type,
				'location'      => $location,
			);
			$message = MessageBuilder::build(
				(string) Settings::get( 'wa_template_exchange' ),
				array(
					'type'     => EnquiryPostType::exchange_type_label( $exchange_type ),
					'brand'    => $brand['name'],
					'size'     => $size,
					'location' => $location,
				)
			);
		} else {
			return self::invalid( array( 'type' => __( 'Choose an enquiry type.', 'lpg-commerce-core' ) ) );
		}//end if

		$id = EnquiryPostType::create( $data );
		if ( is_wp_error( $id ) ) {
			return new \WP_Error( 'lpg_save_failed', __( 'Sorry, your enquiry could not be saved. Please contact us by phone.', 'lpg-commerce-core' ), array( 'status' => 500 ) );
		}

		return new \WP_REST_Response(
			array(
				'id'           => $id,
				'whatsapp_url' => MessageBuilder::link( (string) Settings::get( 'whatsapp_number' ), $message ),
			),
			201
		);
	}
}
