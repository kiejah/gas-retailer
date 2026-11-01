<?php
/**
 * POST lpg/v1/rfq and the RFQ notification.
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgRecordFixtures.php';

use LPG\Commerce\Enquiries\Notifications;
use LPG\Commerce\Enquiries\RfqPostType;
use LPG\Commerce\Rest\FormController;
use LPG\Commerce\Taxonomies\Segment;

/**
 * Tests for LPG\Commerce\Rest\RfqController (§13.4) and §13.7 RFQ emails.
 */
class RfqRestTest extends WP_UnitTestCase {

	use LpgRecordFixtures;

	public function set_up(): void {
		parent::set_up();
		Segment::seed_terms();
		$this->boot_records();
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_wp_mail' );
		remove_all_actions( 'phpmailer_init' );
		$this->shutdown_records();
		parent::tear_down();
	}

	public function test_route_is_registered_publicly_with_schema(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/lpg/v1/rfq', $routes );
		$handler = $routes['/lpg/v1/rfq'][0];
		$this->assertSame( '__return_true', $handler['permission_callback'] );
		$this->assertSame( 'email', $handler['args']['contact']['properties']['email']['format'] );
		$this->assertSame( 10000, $handler['args']['items']['items']['properties']['qty']['maximum'] );
	}

	public function test_valid_request_creates_rfq_with_consent_and_sanitised_fields(): void {
		$product  = $this->product();
		$body     = $this->rfq_body(
			array(
				'items' => array(
					array(
						'description' => '<b>13kg</b> cylinders',
						'qty'         => '10',
					),
					array(
						'product_id' => $product,
						'qty'        => 2,
					),
				),
			)
		);
		$response = $this->post_route( '/rfq', $body );

		$this->assertSame( 201, $response->get_status() );
		$id = $response->get_data()['id'];
		$this->assertGreaterThan( 0, $id );
		$this->assertNotEmpty( $response->get_data()['message'] );
		$this->assertSame( RfqPostType::POST_TYPE, get_post_type( $id ) );
		$this->assertSame( 'lpg-new', get_post_status( $id ) );

		$rfq = RfqPostType::get( $id );
		$this->assertSame( '254700000002', $rfq['contact']['phone'], 'Phone normalised to 254…' );
		$this->assertSame( 'commercial', $rfq['segment'] );
		$this->assertSame( '13kg cylinders', $rfq['items'][0]['description'] );
		$this->assertSame( 10, $rfq['items'][0]['qty'] );
		$this->assertSame( '[QA TEST] Regulator & Hose', $rfq['items'][1]['description'], 'Product name fills an empty description.' );
		$this->assertSame( "Call after 2pm.\nThanks", $rfq['comments'] );
		$this->assertSame( 'draft-2026-11-01', $rfq['consent']['policy_version'] );
		$this->assertNotFalse( strtotime( $rfq['consent']['at'] ) );
	}

	public function test_kenyan_phone_formats(): void {
		$this->assertSame( '254712345678', FormController::normalise_ke_phone( '0712 345 678' ) );
		$this->assertSame( '254112345678', FormController::normalise_ke_phone( '+254 112-345-678' ) );
		$this->assertSame( '254201234567', FormController::normalise_ke_phone( '(020) 1234567' ) );
		$this->assertSame( '', FormController::normalise_ke_phone( '712345678' ) );
		$this->assertSame( '', FormController::normalise_ke_phone( '+1 555 010 0000' ) );
		$this->assertSame( '', FormController::normalise_ke_phone( '07123' ) );
	}

	/**
	 * Invalid bodies and the param each must be reported under.
	 *
	 * @return array<string, array{0: callable, 1: string}>
	 */
	public function invalid_bodies(): array {
		$contact = static fn( array $c ) => static fn( array $b ) => array_merge( $b, array( 'contact' => array_merge( $b['contact'], $c ) ) );
		$item    = static fn( array $i ) => static fn( array $b ) => array_merge( $b, array( 'items' => array( $i ) ) );
		return array(
			'no contact'         => array( static fn( array $b ) => array_diff_key( $b, array( 'contact' => 1 ) ), 'contact.email' ),
			'short name'         => array( $contact( array( 'name' => 'A' ) ), 'contact.name' ),
			'blank name'         => array( $contact( array( 'name' => '  <b></b>  ' ) ), 'contact.name' ),
			'bad phone'          => array( $contact( array( 'phone' => 'call me' ) ), 'contact.phone' ),
			'foreign phone'      => array( $contact( array( 'phone' => '+1 555 010 0000' ) ), 'contact.phone' ),
			'bad email'          => array( $contact( array( 'email' => 'not-an-email' ) ), 'contact.email' ),
			'unknown contact'    => array( $contact( array( 'fax' => '1' ) ), 'contact' ),
			'no segment'         => array( static fn( array $b ) => array_diff_key( $b, array( 'segment' => 1 ) ), 'segment' ),
			'unknown segment'    => array( static fn( array $b ) => array_merge( $b, array( 'segment' => 'military' ) ), 'segment' ),
			'no items'           => array( static fn( array $b ) => array_diff_key( $b, array( 'items' => 1 ) ), 'items' ),
			'empty items'        => array( static fn( array $b ) => array_merge( $b, array( 'items' => array() ) ), 'items' ),
			'qty zero'           => array(
				$item(
					array(
						'description' => 'x',
						'qty'         => 0,
					)
				),
				'items.0.qty',
			),
			'qty too big'        => array(
				$item(
					array(
						'description' => 'x',
						'qty'         => 10001,
					)
				),
				'items.0.qty',
			),
			'no qty'             => array( $item( array( 'description' => 'x' ) ), 'items.0.qty' ),
			'no description'     => array( $item( array( 'qty' => 1 ) ), 'items.0.description' ),
			'long description'   => array(
				$item(
					array(
						'description' => str_repeat( 'x', 501 ),
						'qty'         => 1,
					)
				),
				'items.0.description',
			),
			'missing product'    => array(
				$item(
					array(
						'product_id' => 999999,
						'qty'        => 1,
					)
				),
				'items.0.product_id',
			),
			'no consent'         => array( static fn( array $b ) => array_diff_key( $b, array( 'consent' => 1 ) ), 'consent' ),
			'consent false'      => array( static fn( array $b ) => array_merge( $b, array( 'consent' => false ) ), 'consent' ),
			'long requirements'  => array( static fn( array $b ) => array_merge( $b, array( 'requirements' => str_repeat( 'x', 2001 ) ) ), 'requirements' ),
			'long location'      => array( static fn( array $b ) => array_merge( $b, array( 'location' => str_repeat( 'x', 201 ) ) ), 'location' ),
			'items not an array' => array( static fn( array $b ) => array_merge( $b, array( 'items' => 'lots' ) ), 'items' ),
		);
	}

	/**
	 * Every validation rule.
	 *
	 * @dataProvider invalid_bodies
	 *
	 * @param callable $mutate Body mutator.
	 * @param string   $param  Expected error param.
	 */
	public function test_validation_errors( callable $mutate, string $param ): void {
		$response = $this->post_route( '/rfq', $mutate( $this->rfq_body() ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
		$this->assertArrayHasKey( $param, $response->get_data()['data']['params'] );
		$this->assertSame( 0, $this->count_records( RfqPostType::POST_TYPE ) );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_schema_errors_name_the_field_in_plain_words(): void {
		$body                    = $this->rfq_body();
		$body['contact']['name'] = 'A';
		$body['items'][0]['qty'] = 0;
		$params                  = $this->post_route( '/rfq', $body )->get_data()['data']['params'];

		$this->assertStringStartsWith( 'Name ', $params['contact.name'] );
		$this->assertStringStartsWith( 'Quantity ', $params['items.0.qty'] );
	}

	public function test_draft_product_is_rejected(): void {
		$draft    = $this->product( 'draft' );
		$response = $this->post_route(
			'/rfq',
			$this->rfq_body(
				array(
					'items' => array(
						array(
							'product_id' => $draft,
							'qty'        => 1,
						),
					),
				)
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertArrayHasKey( 'items.0.product_id', $response->get_data()['data']['params'] );
	}

	public function test_missing_or_bad_nonce_is_rejected(): void {
		foreach ( array( false, '', 'abc123', wp_create_nonce( 'some_other_action' ) ) as $nonce ) {
			$response = $this->post_route( '/rfq', $this->rfq_body(), $nonce );
			$this->assertSame( 403, $response->get_status(), var_export( $nonce, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			$this->assertSame( 'rest_cookie_invalid_nonce', $response->get_data()['code'] );
		}
		$this->assertSame( 0, $this->count_records( RfqPostType::POST_TYPE ) );
	}

	public function test_nonce_from_another_user_is_rejected(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$nonce = wp_create_nonce( 'wp_rest' );
		wp_set_current_user( 0 );

		$this->assertSame( 403, $this->post_route( '/rfq', $this->rfq_body(), $nonce )->get_status() );
	}

	public function test_honeypot_fakes_success_and_stores_nothing(): void {
		$body     = $this->rfq_body(
			array(
				'hp'      => 'http://spam.example.test',
				'consent' => 'nonsense',
				'items'   => 'invalid',
			)
		);
		$response = $this->post_route( '/rfq', $body );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 0, $response->get_data()['id'] );
		$this->assertSame( 0, $this->count_records( RfqPostType::POST_TYPE ) );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_rate_limit_allows_five_per_ip_per_route(): void {
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->assertSame( 201, $this->post_route( '/rfq', $this->rfq_body( array( 'hp' => 'x' ) ) )->get_status(), "call $i" );
		}
		$sixth = $this->post_route( '/rfq', $this->rfq_body() );
		$this->assertSame( 429, $sixth->get_status() );
		$this->assertSame( 'lpg_rate_limited', $sixth->get_data()['code'] );
		$this->assertGreaterThan( 0, (int) $sixth->get_headers()['Retry-After'] );
		$this->assertSame( 0, $this->count_records( RfqPostType::POST_TYPE ) );

		// Another route and another IP have their own budgets.
		$this->assertSame( 400, $this->post_route( '/enquiry', array( 'type' => 'bogus' ) )->get_status() );
		$_SERVER['REMOTE_ADDR'] = '192.0.2.11';
		$this->assertSame( 201, $this->post_route( '/rfq', $this->rfq_body() )->get_status() );
	}

	public function test_rate_limit_ignores_forwarded_for_header(): void {
		for ( $i = 1; $i <= 5; $i++ ) {
			$_SERVER['HTTP_X_FORWARDED_FOR'] = "198.51.100.$i";
			$this->post_route( '/rfq', $this->rfq_body( array( 'hp' => 'x' ) ) );
		}
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.99';
		$this->assertSame( 429, $this->post_route( '/rfq', $this->rfq_body() )->get_status() );
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	public function test_notification_to_sales_cc_owner_reply_to_customer(): void {
		$response = $this->post_route( '/rfq', $this->rfq_body() );
		$id       = $response->get_data()['id'];

		$mailer = tests_retrieve_phpmailer_instance();
		$sent   = $mailer->get_sent( 0 );
		$this->assertNotFalse( $sent );
		$this->assertSame( 'sales@example.test', $sent->to[0][0] );
		$this->assertSame( 'owner@example.test', $sent->cc[0][0] );
		$this->assertSame( array( 'buyer@example.test' => 'Test Buyer' ), array_column( $mailer->getReplyToAddresses(), 1, 0 ) );
		$this->assertStringContainsString( 'Reply-To: Test Buyer <buyer@example.test>', $sent->header );
		$this->assertStringContainsString( "New quote request #{$id}: Example Hotel Ltd", $sent->subject );
		$this->assertStringContainsString( 'Content-Type: text/plain', $sent->header );
		$this->assertStringContainsString( '10 × 13kg cylinders', $sent->body );
		$this->assertStringContainsString( "post.php?post={$id}&action=edit", $sent->body );
		$this->assertFalse( $mailer->get_sent( 1 ), 'One email only.' );
	}

	public function test_notification_skips_invalid_cc_and_falls_back_to_owner(): void {
		$this->set_settings( array( 'notify_owner_email' => 'broken' ) );
		$this->post_route( '/rfq', $this->rfq_body() );
		$sent = tests_retrieve_phpmailer_instance()->get_sent( 0 );
		$this->assertSame( 'sales@example.test', $sent->to[0][0] );
		$this->assertSame( array(), $sent->cc );

		reset_phpmailer_instance();
		$this->set_settings( array( 'notify_sales_email' => '' ) );
		$this->post_route( '/rfq', $this->rfq_body() );
		$this->assertSame( 'owner@example.test', tests_retrieve_phpmailer_instance()->get_sent( 0 )->to[0][0] );

		reset_phpmailer_instance();
		$this->set_settings(
			array(
				'notify_sales_email' => '',
				'notify_owner_email' => '',
			)
		);
		$this->assertSame( 201, $this->post_route( '/rfq', $this->rfq_body() )->get_status() );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_header_injection_is_stripped(): void {
		$this->assertSame( 'a b', Notifications::header_safe( "a\r\nb" ) );

		// Hostile values stored directly (bypassing the REST sanitisers).
		$id     = RfqPostType::create(
			array(
				'contact' => array(
					'name'         => "Eve\r\nBcc: victim@example.test",
					'organisation' => "Org\nBcc: victim2@example.test",
					'phone'        => '254700000003',
					'email'        => "eve@example.test\r\nBcc: victim3@example.test",
				),
				'segment' => 'household',
				'items'   => array(),
			)
		);
		$mailer = tests_retrieve_phpmailer_instance();
		$sent   = $mailer->get_sent( 0 );

		$this->assertDoesNotMatchRegularExpression( '/[\r\n]/', $sent->subject );
		$this->assertStringContainsString( "#{$id}", $sent->subject );
		$this->assertSame( array(), $mailer->getBccAddresses() );
		$this->assertDoesNotMatchRegularExpression( '/^(Bcc|Cc):.*victim/mi', $sent->header );
		$this->assertSame( array(), $mailer->getReplyToAddresses(), 'An invalid customer email gets no Reply-To.' );
	}

	public function test_mail_failure_still_returns_201(): void {
		add_filter( 'pre_wp_mail', '__return_false' );
		$this->assertSame( 201, $this->post_route( '/rfq', $this->rfq_body() )->get_status() );
		remove_filter( 'pre_wp_mail', '__return_false' );

		add_action(
			'phpmailer_init',
			static function () {
				throw new RuntimeException( 'SMTP down' );
			}
		);
		$response = $this->post_route( '/rfq', $this->rfq_body() );
		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 2, $this->count_records( RfqPostType::POST_TYPE ) );
	}

	public function test_send_rfq_reports_whether_mail_was_attempted(): void {
		$id = RfqPostType::create( array( 'contact' => array( 'email' => 'buyer@example.test' ) ) );
		reset_phpmailer_instance();

		$this->assertTrue( Notifications::send_rfq( $id ) );
		add_filter( 'pre_wp_mail', '__return_false' );
		$this->assertFalse( Notifications::send_rfq( $id ) );
	}
}
