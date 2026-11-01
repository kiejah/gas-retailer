<?php
/**
 * Personal data exporter/eraser and retention clean-up.
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgRecordFixtures.php';

use LPG\Commerce\Enquiries\EnquiryPostType;
use LPG\Commerce\Enquiries\RfqPostType;
use LPG\Commerce\Privacy\Exporters;
use LPG\Commerce\Privacy\Retention;

/**
 * Tests for LPG\Commerce\Privacy\Exporters and Retention (§13.8).
 */
class RecordPrivacyTest extends WP_UnitTestCase {

	use LpgRecordFixtures;

	public function set_up(): void {
		parent::set_up();
		$this->boot_records();
	}

	public function tear_down(): void {
		$this->shutdown_records();
		parent::tear_down();
	}

	private function rfq( string $email, string $name = 'Test Buyer' ): int {
		return RfqPostType::create(
			array(
				'contact' => array(
					'name'  => $name,
					'phone' => '254700000002',
					'email' => $email,
				),
				'items'   => array(
					array(
						'product_id'  => 0,
						'description' => 'Burner',
						'qty'         => 4,
					),
				),
				'consent' => array(
					'at'             => '2026-11-02T10:00:00+00:00',
					'policy_version' => 'draft-2026-11-01',
				),
			)
		);
	}

	/**
	 * Create a record dated $months_ago months back with a status.
	 *
	 * @param string $type       Post type.
	 * @param string $status     Status.
	 * @param int    $months_ago Age in months.
	 */
	private function aged( string $type, string $status, int $months_ago ): int {
		$id   = RfqPostType::POST_TYPE === $type ? $this->rfq( 'aged@example.test' ) : EnquiryPostType::create(
			array(
				'type'  => 'exchange',
				'brand' => 'K-Gas',
				'size'  => '6kg',
			)
		);
		$date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$months_ago} months -1 day" ) );
		wp_update_post(
			array(
				'ID'            => $id,
				'post_status'   => $status,
				'post_date'     => $date,
				'post_date_gmt' => $date,
			)
		);
		return $id;
	}

	public function test_exporter_and_eraser_are_registered(): void {
		$this->assertArrayHasKey( Exporters::ID, apply_filters( 'wp_privacy_personal_data_exporters', array() ) );
		$this->assertArrayHasKey( Exporters::ID, apply_filters( 'wp_privacy_personal_data_erasers', array() ) );
	}

	public function test_export_returns_matching_requests_only(): void {
		$mine   = $this->rfq( 'buyer@example.test' );
		$upper  = $this->rfq( 'Buyer@Example.test', 'Second' );
		$other  = $this->rfq( 'other@example.test' );
		$prefix = $this->rfq( 'xbuyer@example.test' );

		$export = Exporters::export( 'buyer@example.test', 1 );

		$this->assertTrue( $export['done'] );
		$this->assertSame( array( "lpg-rfq-{$mine}", "lpg-rfq-{$upper}" ), wp_list_pluck( $export['data'], 'item_id' ) );
		$first = array_column( $export['data'][0]['data'], 'value', 'name' );
		$this->assertSame( 'Test Buyer', $first['Name'] );
		$this->assertSame( 'buyer@example.test', $first['Email'] );
		$this->assertSame( '254700000002', $first['Phone'] );
		$this->assertSame( '4 × Burner', $first['Items'] );
		$this->assertSame( 'draft-2026-11-01', $first['Privacy policy version'] );
		$this->assertSame( 'lpg-quote-requests', $export['data'][0]['group_id'] );
		$this->assertNotContains( "lpg-rfq-{$other}", wp_list_pluck( $export['data'], 'item_id' ) );
		$this->assertNotContains( "lpg-rfq-{$prefix}", wp_list_pluck( $export['data'], 'item_id' ) );

		$this->assertSame( array(), Exporters::export( 'nobody@example.test', 1 )['data'] );
		$this->assertSame( array(), Exporters::export( 'not an email', 1 )['data'] );
	}

	public function test_export_finds_records_in_every_status(): void {
		$id = $this->rfq( 'buyer@example.test' );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'lpg-quoted',
			)
		);
		$this->assertCount( 1, Exporters::export( 'buyer@example.test', 1 )['data'] );
	}

	public function test_eraser_deletes_matching_requests(): void {
		$mine  = $this->rfq( 'buyer@example.test' );
		$other = $this->rfq( 'other@example.test' );

		$result = Exporters::erase( 'buyer@example.test', 1 );

		$this->assertTrue( $result['items_removed'] );
		$this->assertFalse( $result['items_retained'] );
		$this->assertTrue( $result['done'] );
		$this->assertNull( get_post( $mine ) );
		$this->assertNotNull( get_post( $other ) );

		$again = Exporters::erase( 'buyer@example.test', 1 );
		$this->assertFalse( $again['items_removed'] );
		$this->assertTrue( $again['done'] );
	}

	public function test_retention_does_nothing_when_off(): void {
		$this->set_settings( array( 'enquiry_retention_months' => 0 ) );
		$old = $this->aged( EnquiryPostType::POST_TYPE, 'lpg-handled', 60 );

		$this->assertSame( 0, Retention::run() );
		$this->assertNotNull( get_post( $old ) );
	}

	public function test_default_retention_is_off(): void {
		update_option( 'lpg_settings', array( 'whatsapp_number' => '254700000001' ), false );
		$old = $this->aged( RfqPostType::POST_TYPE, 'lpg-closed', 60 );

		$this->assertSame( 0, Retention::run() );
		$this->assertNotNull( get_post( $old ) );
	}

	public function test_retention_deletes_only_old_records_in_final_statuses(): void {
		$this->set_settings( array( 'enquiry_retention_months' => 12 ) );

		$delete = array(
			$this->aged( RfqPostType::POST_TYPE, 'lpg-new', 13 ),
			$this->aged( RfqPostType::POST_TYPE, 'lpg-closed', 24 ),
			$this->aged( EnquiryPostType::POST_TYPE, 'lpg-new', 13 ),
			$this->aged( EnquiryPostType::POST_TYPE, 'lpg-handled', 13 ),
		);
		$keep   = array(
			$this->aged( RfqPostType::POST_TYPE, 'lpg-review', 24 ),
			$this->aged( RfqPostType::POST_TYPE, 'lpg-quoted', 24 ),
			$this->aged( RfqPostType::POST_TYPE, 'lpg-closed', 11 ),
			$this->aged( EnquiryPostType::POST_TYPE, 'lpg-handled', 6 ),
			$this->aged( EnquiryPostType::POST_TYPE, 'trash', 24 ),
			self::factory()->post->create( array( 'post_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-5 years' ) ) ) ),
		);

		$this->assertSame( 4, Retention::run() );
		foreach ( $delete as $id ) {
			$this->assertNull( get_post( $id ), "#$id should be deleted" );
		}
		foreach ( $keep as $id ) {
			$this->assertNotNull( get_post( $id ), "#$id should be kept" );
		}
	}

	public function test_retention_runs_on_its_cron_hook(): void {
		$this->assertNotFalse( has_action( Retention::HOOK, array( Retention::class, 'run' ) ) );
	}
}
