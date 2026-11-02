<?php
/**
 * C22 M1 rate limiting, L1 email characters.
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgRecordFixtures.php';

use LPG\Commerce\Enquiries\RfqPostType;
use LPG\Commerce\Rest\ClientIp;
use LPG\Commerce\Taxonomies\CylinderBrand;
use LPG\Commerce\Taxonomies\Segment;

/**
 * Tests for ClientIp, RateCounter and the limits in FormController.
 */
class RateLimitTest extends WP_UnitTestCase {

	use LpgRecordFixtures;

	public function set_up(): void {
		parent::set_up();
		Segment::seed_terms();
		$this->boot_records();
	}

	public function tear_down(): void {
		remove_all_filters( 'lpg_global_rate_limit' );
		$this->shutdown_records();
		parent::tear_down();
	}

	// ----- ClientIp --------------------------------------------------------------------

	public function test_remote_addr_only_without_trusted_proxies(): void {
		$server = array(
			'REMOTE_ADDR'           => '192.0.2.10',
			'HTTP_X_FORWARDED_FOR'  => '198.51.100.1',
			'HTTP_CF_CONNECTING_IP' => '198.51.100.2',
		);
		$this->assertSame( '192.0.2.10', ClientIp::resolve( $server, array() ) );
		$this->assertSame( '192.0.2.10', ClientIp::resolve( $server, array( '203.0.113.0/24' ) ), 'Peer is not a trusted proxy.' );
		$this->assertSame( '', ClientIp::resolve( array( 'REMOTE_ADDR' => 'nonsense' ), array() ) );
	}

	public function test_trusted_proxy_uses_cf_header_then_rightmost_untrusted_forwarded_for(): void {
		$trusted = array( '203.0.113.0/24', '2001:db8:ffff::/48' );

		$this->assertSame(
			'198.51.100.7',
			ClientIp::resolve(
				array(
					'REMOTE_ADDR'           => '203.0.113.5',
					'HTTP_CF_CONNECTING_IP' => '198.51.100.7',
					'HTTP_X_FORWARDED_FOR'  => '10.0.0.1',
				),
				$trusted
			)
		);
		$this->assertSame(
			'198.51.100.9',
			ClientIp::resolve(
				array(
					'REMOTE_ADDR'          => '203.0.113.5',
					'HTTP_X_FORWARDED_FOR' => '1.1.1.1, 198.51.100.9, 203.0.113.6',
				),
				$trusted
			),
			'Spoofed left-most entry ignored; trusted hops skipped.'
		);
		$this->assertSame(
			'2001:db8:1::5',
			ClientIp::resolve(
				array(
					'REMOTE_ADDR'          => '2001:db8:ffff::1',
					'HTTP_X_FORWARDED_FOR' => '2001:db8:1::5',
				),
				$trusted
			)
		);
		$this->assertSame(
			'203.0.113.5',
			ClientIp::resolve(
				array(
					'REMOTE_ADDR'          => '203.0.113.5',
					'HTTP_X_FORWARDED_FOR' => 'garbage',
				),
				$trusted
			),
			'Malformed header falls back to the proxy address.'
		);
	}

	public function test_cidr_matching_and_ipv6_buckets(): void {
		$this->assertTrue( ClientIp::in_ranges( '198.51.100.200', array( '198.51.100.128/25' ) ) );
		$this->assertFalse( ClientIp::in_ranges( '198.51.100.100', array( '198.51.100.128/25' ) ) );
		$this->assertTrue( ClientIp::in_ranges( '192.0.2.1', array( '192.0.2.1' ) ) );
		$this->assertFalse( ClientIp::in_ranges( '192.0.2.1', array( '2001:db8::/32' ) ) );

		$this->assertSame( '192.0.2.10', ClientIp::bucket( '192.0.2.10' ) );
		$this->assertSame( ClientIp::bucket( '2001:db8:0:0:1::1' ), ClientIp::bucket( '2001:db8::ffff:ffff:ffff:2' ), 'Same /64.' );
		$this->assertNotSame( ClientIp::bucket( '2001:db8::1' ), ClientIp::bucket( '2001:db8:0:1::1' ), 'Different /64.' );
		$this->assertSame( '192.0.2.10', ClientIp::bucket( '::ffff:192.0.2.10' ) );
		$this->assertSame( 'unknown', ClientIp::bucket( '' ) );
	}

	// ----- Per-IP buckets --------------------------------------------------------------

	public function test_ipv6_clients_share_a_budget_per_64(): void {
		$_SERVER['REMOTE_ADDR'] = '2001:db8::1';
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->assertSame( 201, $this->post_route( '/rfq', $this->rfq_body() )->get_status(), "call $i" );
		}
		$_SERVER['REMOTE_ADDR'] = '2001:db8::abcd';
		$this->assertSame( 429, $this->post_route( '/rfq', $this->rfq_body() )->get_status(), 'Same /64.' );
		$_SERVER['REMOTE_ADDR'] = '2001:db8:0:1::1';
		$this->assertSame( 201, $this->post_route( '/rfq', $this->rfq_body() )->get_status(), 'Next /64.' );
	}

	public function test_invalid_bodies_use_a_separate_budget(): void {
		for ( $i = 1; $i <= 10; $i++ ) {
			$this->assertSame( 400, $this->post_route( '/rfq', array( 'segment' => 'x' ) )->get_status() );
		}
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->assertSame( 201, $this->post_route( '/rfq', $this->rfq_body() )->get_status(), "Valid call $i after junk." );
		}
		$this->assertSame( 429, $this->post_route( '/rfq', $this->rfq_body() )->get_status() );
	}

	public function test_twenty_invalid_bodies_block_the_ip(): void {
		for ( $i = 1; $i <= 20; $i++ ) {
			$this->assertSame( 400, $this->post_route( '/rfq', array( 'segment' => 'x' ) )->get_status(), "invalid $i" );
		}
		$this->assertSame( 429, $this->post_route( '/rfq', $this->rfq_body() )->get_status() );
		$this->assertSame( 0, $this->count_records( RfqPostType::POST_TYPE ) );

		$_SERVER['REMOTE_ADDR'] = '192.0.2.99';
		$this->assertSame( 201, $this->post_route( '/rfq', $this->rfq_body() )->get_status(), 'Other visitors unaffected.' );
	}

	public function test_honeypot_hits_do_not_count(): void {
		for ( $i = 1; $i <= 12; $i++ ) {
			$this->assertSame( 201, $this->post_route( '/rfq', array( 'hp' => 'bot' ) )->get_status() );
		}
		$this->assertSame( 201, $this->post_route( '/rfq', $this->rfq_body() )->get_status() );
		$this->assertSame( 1, $this->count_records( RfqPostType::POST_TYPE ) );
	}

	// ----- Global ceiling --------------------------------------------------------------

	public function test_default_global_limits(): void {
		$seen = array();
		add_filter(
			'lpg_global_rate_limit',
			static function ( $limit, $route ) use ( &$seen ) {
				$seen[ $route ] = $limit;
				return $limit;
			},
			10,
			2
		);
		$this->post_route( '/rfq', $this->rfq_body() );
		CylinderBrand::seed();
		$this->post_route(
			'/enquiry',
			array(
				'type'          => 'exchange',
				'brand'         => 'k-gas',
				'size'          => '6kg',
				'exchange_type' => 'exchange',
				'location'      => 'Westlands',
			)
		);
		$this->assertSame(
			array(
				'rfq'     => 30,
				'enquiry' => 60,
			),
			$seen
		);
	}

	public function test_above_global_ceiling_records_are_saved_but_mail_becomes_one_alert(): void {
		add_filter( 'lpg_global_rate_limit', static fn( $limit, $route ) => 'rfq' === $route ? 2 : $limit, 10, 2 );

		foreach ( array( '192.0.2.21', '192.0.2.22', '192.0.2.23', '192.0.2.24' ) as $ip ) {
			$_SERVER['REMOTE_ADDR'] = $ip;
			$this->assertSame( 201, $this->post_route( '/rfq', $this->rfq_body() )->get_status(), $ip );
		}

		$this->assertSame( 4, $this->count_records( RfqPostType::POST_TYPE ), 'Every lead is saved.' );

		$mailer   = tests_retrieve_phpmailer_instance();
		$subjects = array();
		$alert    = null;
		foreach ( range( 0, 9 ) as $i ) {
			$sent = $mailer->get_sent( $i );
			if ( ! $sent ) {
				break;
			}
			$subjects[] = $sent->subject;
			if ( false !== strpos( $sent->subject, 'Unusual volume' ) ) {
				$alert = $sent;
			}
		}
		$this->assertCount( 3, $subjects, 'Two notifications, then one alert, then nothing.' );
		$this->assertStringContainsString( 'New quote request', $subjects[0] );
		$this->assertStringContainsString( 'New quote request', $subjects[1] );
		$this->assertStringContainsString( 'Unusual volume of quote requests', $subjects[2] );
		$this->assertSame( 'sales@example.test', $alert->to[0][0] );
		$this->assertStringContainsString( 'About 3 quote requests', $alert->body );
		$this->assertStringNotContainsString( 'buyer@example.test', $alert->body, 'No customer data.' );
		$this->assertStringNotContainsString( 'Test Buyer', $alert->body );
		$this->assertStringNotContainsString( 'Example Hotel', $alert->body );
	}

	// ----- L1 --------------------------------------------------------------------------

	public function test_emails_with_mailto_unsafe_characters_are_rejected(): void {
		foreach ( array( 'a?cc=x@example.test', 'a&b@example.test', 'a%40@example.test', 'a=b@example.test', 'a/b@example.test' ) as $email ) {
			$body                     = $this->rfq_body();
			$body['contact']['email'] = $email;
			$response                 = $this->post_route( '/rfq', $body );
			$this->assertSame( 400, $response->get_status(), $email );
			$this->assertArrayHasKey( 'contact.email', $response->get_data()['data']['params'], $email );
		}
		$this->assertSame( 0, $this->count_records( RfqPostType::POST_TYPE ) );
	}
}
