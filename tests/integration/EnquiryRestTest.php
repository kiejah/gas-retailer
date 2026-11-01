<?php
/**
 * POST lpg/v1/enquiry and the exchange notification.
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgRecordFixtures.php';

use LPG\Commerce\Enquiries\EnquiryPostType;
use LPG\Commerce\Taxonomies\CylinderBrand;
use LPG\Commerce\WhatsApp\MessageBuilder;

/**
 * Tests for LPG\Commerce\Rest\EnquiryController (§13.4, §13.5, §13.7).
 */
class EnquiryRestTest extends WP_UnitTestCase {

	use LpgRecordFixtures;

	public function set_up(): void {
		parent::set_up();
		CylinderBrand::seed();
		$this->boot_records();
	}

	public function tear_down(): void {
		$this->shutdown_records();
		parent::tear_down();
	}

	private function exchange( array $overrides = array() ): array {
		return array_merge(
			array(
				'type'          => 'exchange',
				'brand'         => 'k-gas',
				'size'          => '13kg',
				'exchange_type' => 'exchange',
				'location'      => 'Kilimani, near the mall',
			),
			$overrides
		);
	}

	public function test_product_enquiry_is_logged_and_returns_link_from_template(): void {
		$product  = $this->product( 'publish', 'QA-REG-1' );
		$response = $this->post_route(
			'/enquiry',
			array(
				'type'       => 'product',
				'product_id' => $product,
			)
		);

		$this->assertSame( 201, $response->get_status() );
		$id = $response->get_data()['id'];
		$this->assertSame( 'lpg-new', get_post_status( $id ) );
		$this->assertSame( 'product', EnquiryPostType::get( $id )['type'] );
		$this->assertSame( $product, EnquiryPostType::get( $id )['product_id'] );

		$expected = MessageBuilder::link(
			'254700000001',
			"Hello, I'm interested in [QA TEST] Regulator & Hose (QA-REG-1). " . get_permalink( $product )
		);
		$this->assertSame( $expected, $response->get_data()['whatsapp_url'] );
		$this->assertStringStartsWith( 'https://wa.me/254700000001?text=Hello%2C', $expected );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent(), 'Product enquiries are logged only.' );
	}

	public function test_exchange_enquiry_is_logged_emailed_and_uses_custom_template(): void {
		$this->set_settings( array( 'wa_template_exchange' => 'Cylinder {type} {brand} {size} to {location} {unknown}' ) );
		$response = $this->post_route( '/enquiry', $this->exchange( array( 'exchange_type' => 'new' ) ) );

		$this->assertSame( 201, $response->get_status() );
		$id      = $response->get_data()['id'];
		$enquiry = EnquiryPostType::get( $id );
		$this->assertSame( 'K-Gas', $enquiry['brand'], 'Stored by brand name.' );
		$this->assertSame( '13kg', $enquiry['size'] );
		$this->assertSame( 'new', $enquiry['exchange_type'] );
		$this->assertSame( 'Kilimani, near the mall', $enquiry['location'] );
		$this->assertSame(
			MessageBuilder::link( '254700000001', 'Cylinder purchase (new cylinder) K-Gas 13kg to Kilimani, near the mall' ),
			$response->get_data()['whatsapp_url']
		);

		$sent = tests_retrieve_phpmailer_instance()->get_sent( 0 );
		$this->assertSame( 'sales@example.test', $sent->to[0][0] );
		$this->assertStringContainsString( "Cylinder request #{$id}: K-Gas 13kg", $sent->subject );
		$this->assertStringContainsString( 'Kilimani, near the mall', $sent->body );
	}

	public function test_whatsapp_url_is_empty_without_a_number(): void {
		$this->set_settings( array( 'whatsapp_number' => '' ) );
		$response = $this->post_route( '/enquiry', $this->exchange() );

		$this->assertSame( 201, $response->get_status() );
		$this->assertGreaterThan( 0, $response->get_data()['id'] );
		$this->assertSame( '', $response->get_data()['whatsapp_url'] );
	}

	public function test_brand_added_in_dashboard_is_accepted(): void {
		$term = wp_insert_term( 'QA Test Gas', CylinderBrand::TAXONOMY );
		CylinderBrand::save_sizes( $term['term_id'], array( '50kg' ) );

		$this->assertSame(
			201,
			$this->post_route(
				'/enquiry',
				$this->exchange(
					array(
						'brand' => 'qa-test-gas',
						'size'  => '50kg',
					)
				)
			)->get_status()
		);
	}

	/**
	 * Invalid bodies and the param each must be reported under.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: string}>
	 */
	public function invalid_bodies(): array {
		return array(
			'no type'            => array( array( 'product_id' => 1 ), 'type' ),
			'unknown type'       => array( array( 'type' => 'refund' ), 'type' ),
			'product, no id'     => array( array( 'type' => 'product' ), 'product_id' ),
			'product, bad id'    => array(
				array(
					'type'       => 'product',
					'product_id' => 999999,
				),
				'product_id',
			),
			'product, id string' => array(
				array(
					'type'       => 'product',
					'product_id' => 'abc',
				),
				'product_id',
			),
			'unknown brand'      => array( array( 'brand' => 'no-such-gas' ), 'brand' ),
			'no brand'           => array( array( 'brand' => '' ), 'brand' ),
			'size not offered'   => array( array( 'size' => '50kg' ), 'size' ),
			'no size'            => array( array( 'size' => '' ), 'size' ),
			'bad exchange type'  => array( array( 'exchange_type' => 'swap' ), 'exchange_type' ),
			'no exchange type'   => array( array( 'exchange_type' => '' ), 'exchange_type' ),
			'no location'        => array( array( 'location' => '   ' ), 'location' ),
			'long location'      => array( array( 'location' => str_repeat( 'x', 201 ) ), 'location' ),
		);
	}

	/**
	 * Every validation rule.
	 *
	 * @dataProvider invalid_bodies
	 *
	 * @param array<string, mixed> $overrides Body overrides (exchange body unless type is given).
	 * @param string               $param     Expected error param.
	 */
	public function test_validation_errors( array $overrides, string $param ): void {
		$body     = isset( $overrides['type'] ) || isset( $overrides['product_id'] ) ? $overrides : $this->exchange( $overrides );
		$response = $this->post_route( '/enquiry', $body );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
		$this->assertArrayHasKey( $param, $response->get_data()['data']['params'] );
		$this->assertSame( 0, $this->count_records( EnquiryPostType::POST_TYPE ) );
	}

	public function test_draft_product_is_rejected(): void {
		$response = $this->post_route(
			'/enquiry',
			array(
				'type'       => 'product',
				'product_id' => $this->product( 'draft' ),
			)
		);
		$this->assertSame( 400, $response->get_status() );
		$this->assertArrayHasKey( 'product_id', $response->get_data()['data']['params'] );
	}

	public function test_nonce_is_required(): void {
		$this->assertSame( 403, $this->post_route( '/enquiry', $this->exchange(), false )->get_status() );
		$this->assertSame( 403, $this->post_route( '/enquiry', $this->exchange(), 'bad' )->get_status() );
		$this->assertSame( 0, $this->count_records( EnquiryPostType::POST_TYPE ) );
	}

	public function test_honeypot_and_rate_limit(): void {
		$response = $this->post_route( '/enquiry', $this->exchange( array( 'hp' => 'filled' ) ) );
		$this->assertSame( 201, $response->get_status() );
		$this->assertSame(
			array(
				'id'           => 0,
				'whatsapp_url' => '',
			),
			$response->get_data()
		);
		$this->assertSame( 0, $this->count_records( EnquiryPostType::POST_TYPE ) );

		for ( $i = 2; $i <= 5; $i++ ) {
			$this->assertSame( 201, $this->post_route( '/enquiry', $this->exchange() )->get_status(), "call $i" );
		}
		$this->assertSame( 429, $this->post_route( '/enquiry', $this->exchange() )->get_status() );
		$this->assertSame( 4, $this->count_records( EnquiryPostType::POST_TYPE ) );
	}
}
