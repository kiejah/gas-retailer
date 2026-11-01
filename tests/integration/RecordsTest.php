<?php
/**
 * Quote request / enquiry post types, statuses, caps, install and uninstall.
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgRecordFixtures.php';

use LPG\Commerce\Enquiries\EnquiryPostType;
use LPG\Commerce\Enquiries\RfqPostType;
use LPG\Commerce\Enquiries\Statuses;
use LPG\Commerce\Installer;
use LPG\Commerce\Privacy\Retention;
use LPG\Commerce\Taxonomies\CylinderBrand;

/**
 * Tests for §13.3 records.
 */
class RecordsTest extends WP_UnitTestCase {

	use LpgRecordFixtures;

	public function set_up(): void {
		parent::set_up();
		Installer::activate();
		$this->boot_records();
	}

	public function tear_down(): void {
		$this->shutdown_records();
		parent::tear_down();
	}

	private function rfq(): int {
		return RfqPostType::create(
			array(
				'contact' => array(
					'name'         => 'Test Buyer',
					'organisation' => 'Example Hotel Ltd',
					'phone'        => '254700000002',
					'email'        => 'buyer@example.test',
				),
				'segment' => 'commercial',
				'items'   => array(
					array(
						'product_id'  => 0,
						'description' => 'Burner',
						'qty'         => 2,
					),
				),
			)
		);
	}

	public function test_post_types_are_private_with_custom_caps(): void {
		foreach ( array(
			RfqPostType::POST_TYPE     => 'lpg_rfqs',
			EnquiryPostType::POST_TYPE => 'lpg_enquiries',
		) as $type => $plural ) {
			$object = get_post_type_object( $type );
			$this->assertFalse( $object->public, $type );
			$this->assertFalse( $object->publicly_queryable, $type );
			$this->assertFalse( $object->show_in_rest, $type );
			$this->assertTrue( $object->exclude_from_search, $type );
			$this->assertTrue( $object->show_ui, $type );
			$this->assertTrue( $object->map_meta_cap, $type );
			$this->assertSame( "edit_{$plural}", $object->cap->edit_posts, $type );
			$this->assertSame( 'do_not_allow', $object->cap->create_posts, $type );
			$this->assertFalse( post_type_supports( $type, 'title' ), $type );
			$this->assertFalse( post_type_supports( $type, 'editor' ), $type );
		}
	}

	public function test_statuses_are_registered_for_admin_lists_and_not_public(): void {
		foreach ( array( 'lpg-new', 'lpg-review', 'lpg-quoted', 'lpg-closed', 'lpg-handled' ) as $status ) {
			$object = get_post_status_object( $status );
			$this->assertNotNull( $object, $status );
			$this->assertFalse( $object->public, $status );
			$this->assertTrue( $object->show_in_admin_status_list, $status );
			$this->assertTrue( $object->show_in_admin_all_list, $status );
		}
		$this->assertSame( array( 'lpg-new', 'lpg-review', 'lpg-quoted', 'lpg-closed' ), RfqPostType::statuses() );
		$this->assertSame( array( 'lpg-new', 'lpg-handled' ), EnquiryPostType::statuses() );
		$this->assertSame( 'In review', Statuses::label( 'lpg-review' ) );
	}

	public function test_create_sets_status_meta_and_generated_title(): void {
		$id = $this->rfq();

		$this->assertSame( 'lpg-new', get_post_status( $id ) );
		$this->assertSame( "RFQ #{$id}: Example Hotel Ltd", get_post_field( 'post_title', $id ) );
		$this->assertSame( 0, (int) get_post_field( 'post_author', $id ) );
		$this->assertSame( 'buyer@example.test', RfqPostType::get( $id )['contact']['email'] );

		CylinderBrand::seed();
		$enquiry = EnquiryPostType::create(
			array(
				'type'          => 'exchange',
				'brand'         => 'K-Gas',
				'size'          => '13kg',
				'exchange_type' => 'exchange',
				'location'      => 'Kilimani',
			)
		);
		$this->assertSame( "Enquiry #{$enquiry}: Cylinder refill / exchange, K-Gas 13kg", get_post_field( 'post_title', $enquiry ) );
		$this->assertSame( 'K-Gas', EnquiryPostType::get( $enquiry )['brand'] );
	}

	public function test_low_roles_cannot_list_or_read_records_but_shop_manager_can(): void {
		$rfq     = $this->rfq();
		$enquiry = EnquiryPostType::create(
			array(
				'type'       => 'product',
				'product_id' => $this->product(),
			)
		);

		foreach ( array( 'subscriber', 'customer', 'contributor', 'author', 'editor' ) as $role ) {
			$user = self::factory()->user->create( array( 'role' => $role ) );
			foreach ( array( 'edit_lpg_rfqs', 'edit_lpg_enquiries' ) as $cap ) {
				$this->assertFalse( user_can( $user, $cap ), "$role: $cap" ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
			}
			foreach ( array( $rfq, $enquiry ) as $id ) {
				$this->assertFalse( user_can( $user, 'read_post', $id ), "$role read $id" );
				$this->assertFalse( user_can( $user, 'edit_post', $id ), "$role edit $id" );
			}
		}

		foreach ( array( 'shop_manager', 'administrator' ) as $role ) {
			$user = self::factory()->user->create( array( 'role' => $role ) );
			foreach ( array_merge( RfqPostType::caps(), EnquiryPostType::caps() ) as $cap ) {
				$this->assertTrue( user_can( $user, $cap ), "$role: $cap" ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
			}
			foreach ( array( $rfq, $enquiry ) as $id ) {
				$this->assertTrue( user_can( $user, 'read_post', $id ), "$role read $id" );
				$this->assertTrue( user_can( $user, 'edit_post', $id ), "$role edit $id" );
				$this->assertTrue( user_can( $user, 'delete_post', $id ), "$role delete $id" );
			}
			$this->assertFalse( user_can( $user, get_post_type_object( RfqPostType::POST_TYPE )->cap->create_posts ), "$role cannot create" );
		}
	}

	public function test_upgrade_does_not_regrant_removed_record_caps(): void {
		get_role( 'shop_manager' )->remove_cap( 'edit_lpg_rfqs' );

		update_option( Installer::VERSION_OPTION, '0.3.0', false );
		Installer::install();

		$this->assertFalse( get_role( 'shop_manager' )->has_cap( 'edit_lpg_rfqs' ) );
		Installer::activate();
		$this->assertTrue( get_role( 'shop_manager' )->has_cap( 'edit_lpg_rfqs' ) );
	}

	public function test_activation_schedules_and_deactivation_clears_retention(): void {
		Retention::unschedule();
		Installer::activate();
		$this->assertNotFalse( wp_next_scheduled( Retention::HOOK ) );

		Installer::deactivate();
		$this->assertFalse( wp_next_scheduled( Retention::HOOK ) );
	}

	public function test_uninstall_with_delete_data_removes_records_brands_and_caps(): void {
		CylinderBrand::seed();
		$rfq     = $this->rfq();
		$brand   = get_term_by( 'slug', 'k-gas', CylinderBrand::TAXONOMY );
		$product = $this->product();
		Installer::activate();
		$this->set_settings( array( 'delete_data_on_uninstall' => true ) );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'lpg-commerce-core/lpg-commerce-core.php' );
		}
		include WP_PLUGIN_DIR . '/lpg-commerce-core/uninstall.php';

		$this->assertNull( get_post( $rfq ) );
		$this->assertSame( 0, $this->count_records( RfqPostType::POST_TYPE ) );
		$this->assertSame( '', get_term_meta( $brand->term_id, CylinderBrand::META_SIZES, true ) );
		$this->assertNull( get_term( $brand->term_id ) );
		$this->assertFalse( get_role( 'shop_manager' )->has_cap( 'edit_lpg_rfqs' ) );
		$this->assertFalse( get_role( 'administrator' )->has_cap( 'edit_lpg_enquiries' ) );
		$this->assertFalse( wp_next_scheduled( Retention::HOOK ) );
		$this->assertNotNull( get_post( $product ), 'Products are never touched.' );

		// Restore in-memory roles for later tests (the DB is rolled back).
		Installer::add_caps();
	}
}
