<?php
/**
 * Admin screens for quote requests and enquiries.
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgRecordFixtures.php';

use LPG\Commerce\Enquiries\AdminScreens;
use LPG\Commerce\Enquiries\EnquiryPostType;
use LPG\Commerce\Enquiries\RfqPostType;
use LPG\Commerce\Installer;

/**
 * Tests for LPG\Commerce\Enquiries\AdminScreens (§13.3 admin).
 */
class RecordAdminTest extends WP_UnitTestCase {

	use LpgRecordFixtures;

	public function set_up(): void {
		parent::set_up();
		Installer::activate();
		$this->boot_records();
	}

	public function tear_down(): void {
		$_GET = array();
		$this->shutdown_records();
		parent::tear_down();
	}

	private function as_role( string $role ): int {
		$user = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user );
		return $user;
	}

	private function hostile_rfq(): int {
		return RfqPostType::create(
			array(
				'contact'      => array(
					'name'         => '<script>alert("n")</script>Eve',
					'organisation' => '<img src=x onerror=alert(1)>Org',
					'phone'        => 'javascript:alert(1)//254700000004',
					'email'        => '"><script>alert(2)</script>@example.test',
				),
				'segment'      => '<b>seg</b>',
				'items'        => array(
					array(
						'product_id'  => 0,
						'description' => '<iframe src="//evil.example.test"></iframe>',
						'qty'         => 3,
					),
				),
				'requirements' => "<script>x</script>\nline two",
				'comments'     => '<a href="javascript:alert(3)">click</a>',
				'consent'      => array(
					'at'             => '<svg onload=alert(4)>',
					'policy_version' => '"><b>',
				),
			)
		);
	}

	public function test_change_status_with_nonce_and_capability(): void {
		$rfq = RfqPostType::create( array( 'contact' => array( 'name' => 'Test Buyer' ) ) );
		$this->as_role( 'shop_manager' );
		$nonce = wp_create_nonce( 'lpg_set_status_' . $rfq );

		$this->assertTrue( AdminScreens::change_status( $rfq, 'lpg-quoted', $nonce ) );
		$this->assertSame( 'lpg-quoted', get_post_status( $rfq ) );
		$this->assertSame( "RFQ #{$rfq}: Test Buyer", get_post_field( 'post_title', $rfq ), 'Title unchanged.' );

		$this->assertSame( 'lpg_bad_nonce', AdminScreens::change_status( $rfq, 'lpg-closed', 'bad' )->get_error_code() );
		$this->assertSame( 'lpg_bad_nonce', AdminScreens::change_status( $rfq, 'lpg-closed', wp_create_nonce( 'lpg_set_status_1' ) )->get_error_code() );
		$this->assertSame( 'lpg_bad_status', AdminScreens::change_status( $rfq, 'publish', $nonce )->get_error_code() );
		$this->assertSame( 'lpg_bad_status', AdminScreens::change_status( $rfq, 'lpg-handled', $nonce )->get_error_code(), 'Enquiry status on an RFQ.' );
		$this->assertSame( 'lpg-quoted', get_post_status( $rfq ) );
	}

	public function test_low_roles_cannot_change_status_even_with_their_own_nonce(): void {
		$enquiry = EnquiryPostType::create(
			array(
				'type'       => 'product',
				'product_id' => $this->product(),
			)
		);
		foreach ( array( 'subscriber', 'customer', 'editor' ) as $role ) {
			$this->as_role( $role );
			$result = AdminScreens::change_status( $enquiry, 'lpg-handled', wp_create_nonce( 'lpg_set_status_' . $enquiry ) );
			$this->assertSame( 'lpg_forbidden', $result->get_error_code(), $role );
		}
		$this->assertSame( 'lpg-new', get_post_status( $enquiry ) );
	}

	public function test_change_status_rejects_other_post_types(): void {
		$this->as_role( 'administrator' );
		$post = self::factory()->post->create();
		$this->assertSame( 'lpg_not_found', AdminScreens::change_status( $post, 'lpg-new', wp_create_nonce( 'lpg_set_status_' . $post ) )->get_error_code() );
		$this->assertSame( 'publish', get_post_status( $post ) );
	}

	public function test_status_url_carries_nonce_for_admin_post(): void {
		$url = AdminScreens::status_url( 12, 'lpg-review' );
		$this->assertStringContainsString( 'admin-post.php?action=lpg_set_status', $url );
		$this->assertStringContainsString( 'status=lpg-review', $url );
		$this->assertMatchesRegularExpression( '/_wpnonce=[a-f0-9]{10}/', $url );
	}

	public function test_detail_meta_box_escapes_hostile_values(): void {
		$id = $this->hostile_rfq();
		$this->as_role( 'shop_manager' );

		ob_start();
		( new AdminScreens() )->render_details( get_post( $id ) );
		$html = ob_get_clean();

		foreach ( array( '<script', '<img', '<iframe', '<svg', 'href="javascript:', '"><b>' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, $html, $needle );
		}
		$this->assertStringContainsString( '&lt;script&gt;alert(&quot;n&quot;)&lt;/script&gt;Eve', $html );
		$this->assertStringContainsString( 'href="tel:+1254700000004"', $html, 'Phone link is digits only.' );
		$this->assertStringContainsString( 'https://wa.me/1254700000004?text=', $html );
		$this->assertStringNotContainsString( 'mailto:', $html, 'Invalid email gets no mailto link.' );
		$this->assertStringContainsString( "&lt;script&gt;x&lt;/script&gt;<br />\nline two", $html );
	}

	public function test_detail_meta_box_links_for_clean_record(): void {
		$id = RfqPostType::create(
			array(
				'contact' => array(
					'name'  => 'Test Buyer',
					'phone' => '254700000002',
					'email' => 'buyer@example.test',
				),
			)
		);
		ob_start();
		( new AdminScreens() )->render_details( get_post( $id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'href="mailto:buyer@example.test"', $html );
		$this->assertStringContainsString( 'href="tel:+254700000002"', $html );
		$this->assertStringContainsString( 'Reply on WhatsApp', $html );
		$this->assertStringContainsString( 'https://wa.me/254700000002?text=Hello%20Test%20Buyer', $html );
	}

	public function test_enquiry_details_and_status_box(): void {
		$id = EnquiryPostType::create(
			array(
				'type'          => 'exchange',
				'brand'         => '<b>K-Gas</b>',
				'size'          => '13kg',
				'exchange_type' => 'exchange',
				'location'      => '<script>loc</script>',
			)
		);
		$this->as_role( 'shop_manager' );
		$screens = new AdminScreens();

		ob_start();
		$screens->render_details( get_post( $id ) );
		$screens->render_status( get_post( $id ) );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<b>K-Gas', $html );
		$this->assertStringContainsString( 'Mark as Handled', $html );
		$this->assertStringNotContainsString( 'Mark as New', $html, 'Current status is not offered.' );
		$this->assertStringContainsString( esc_url( AdminScreens::status_url( $id, 'lpg-handled' ) ), $html );

		$this->as_role( 'subscriber' );
		ob_start();
		$screens->render_status( get_post( $id ) );
		$this->assertStringNotContainsString( 'Mark as', ob_get_clean() );
	}

	public function test_list_columns_content_and_filters(): void {
		$screens = new AdminScreens();
		set_current_screen( 'edit-' . RfqPostType::POST_TYPE );
		$this->assertSame(
			array( 'cb', 'title', 'lpg_contact', 'lpg_segment', 'lpg_status', 'date' ),
			array_keys(
				$screens->columns(
					array(
						'cb'   => '',
						'date' => 'Date',
					)
				)
			)
		);
		set_current_screen( 'edit-' . EnquiryPostType::POST_TYPE );
		$this->assertSame( array( 'cb', 'title', 'lpg_type', 'lpg_subject', 'lpg_location', 'lpg_status', 'date' ), array_keys( $screens->columns( array() ) ) );

		$id = $this->hostile_rfq();
		ob_start();
		$screens->column_content( 'lpg_contact', $id );
		$screens->column_content( 'lpg_segment', $id );
		$screens->column_content( 'lpg_status', $id );
		$html = ob_get_clean();
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( 'New', $html );

		$this->assertArrayNotHasKey(
			'edit',
			$screens->bulk_actions(
				array(
					'edit'  => 'Edit',
					'trash' => 'Bin',
				)
			)
		);
		$actions = $screens->row_actions(
			array(
				'inline hide-if-no-js' => 'Quick Edit',
				'trash'                => 'Bin',
			),
			get_post( $id )
		);
		$this->assertSame( array( 'trash' ), array_keys( $actions ) );
	}

	public function test_type_filter_limits_the_admin_list_query(): void {
		$product  = EnquiryPostType::create(
			array(
				'type'       => 'product',
				'product_id' => $this->product(),
			)
		);
		$exchange = EnquiryPostType::create(
			array(
				'type'  => 'exchange',
				'brand' => 'K-Gas',
				'size'  => '6kg',
			)
		);
		set_current_screen( 'edit-' . EnquiryPostType::POST_TYPE );
		$_GET['lpg_type'] = 'exchange';

		$query                   = new WP_Query();
		$GLOBALS['wp_the_query'] = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$ids                     = $query->query(
			array(
				'post_type'   => EnquiryPostType::POST_TYPE,
				'post_status' => EnquiryPostType::statuses(),
				'fields'      => 'ids',
			)
		);
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertSame( array( $exchange ), array_map( 'intval', $ids ) );
		$this->assertNotContains( $product, $ids );

		$_GET['lpg_type'] = 'not-a-type';
		$this->assertCount(
			2,
			get_posts(
				array(
					'post_type'   => EnquiryPostType::POST_TYPE,
					'post_status' => EnquiryPostType::statuses(),
					'fields'      => 'ids',
				)
			)
		);
	}
}
