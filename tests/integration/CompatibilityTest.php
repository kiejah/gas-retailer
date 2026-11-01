<?php
/**
 * Compatible products, two-way sync (§11.4).
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgFixtures.php';

use LPG\Commerce\Relations\Compatibility;

class CompatibilityTest extends WP_UnitTestCase {

	use LpgFixtures;

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * A user who can edit their own products but not other people's.
	 */
	private function limited_user(): int {
		add_role(
			'lpg_test_own_products',
			'Own products only',
			array(
				'read'                    => true,
				'edit_products'           => true,
				'edit_published_products' => true,
			)
		);
		$user = self::factory()->user->create( array( 'role' => 'lpg_test_own_products' ) );
		$this->assertTrue( user_can( $user, 'edit_products' ) ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce cap.
		$this->assertFalse( user_can( $user, 'edit_others_products' ) ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce cap.

		return $user;
	}

	private function owned_by( int $user ): int {
		$id = $this->create_product()->get_id();
		wp_update_post(
			array(
				'ID'          => $id,
				'post_author' => $user,
			)
		);

		return $id;
	}

	public function tear_down(): void {
		remove_role( 'lpg_test_own_products' );
		parent::tear_down();
	}

	public function test_limited_user_cannot_add_or_touch_products_they_cannot_edit(): void {
		$others = $this->create_product()->get_id();
		$kept   = $this->create_product()->get_id();
		$user   = $this->limited_user();
		$mine   = $this->owned_by( $user );
		$also   = $this->owned_by( $user );

		// An admin links $mine to $kept (mirrored onto $kept).
		Compatibility::save( $mine, array( $kept ) );
		$this->assertSame( array( $mine ), Compatibility::stored_ids( $kept ) );

		wp_set_current_user( $user );
		$this->assertTrue( current_user_can( 'edit_post', $mine ) );
		$this->assertFalse( current_user_can( 'edit_post', $others ) );

		// Tries to add a product they cannot edit and to drop $kept.
		Compatibility::save( $mine, array( $others, $also ) );

		$this->assertEqualsCanonicalizing( array( $also, $kept ), Compatibility::stored_ids( $mine ), 'New uneditable ID rejected; stored uneditable ID kept.' );
		$this->assertSame( array(), Compatibility::stored_ids( $others ), 'No mirror onto an uneditable product.' );
		$this->assertSame( array( $mine ), Compatibility::stored_ids( $kept ), 'Uneditable product keeps its link.' );
		$this->assertSame( array( $mine ), Compatibility::stored_ids( $also ), 'Editable product is mirrored.' );
	}

	public function test_permanent_delete_removes_id_from_other_products(): void {
		$a = $this->create_product()->get_id();
		$b = $this->create_product()->get_id();
		$c = $this->create_product()->get_id();
		$d = $this->create_product()->get_id();
		Compatibility::save( $a, array( $b ) );
		add_filter( 'lpg_compatibility_bidirectional', '__return_false' );
		Compatibility::save( $c, array( $a, $d ) );
		remove_filter( 'lpg_compatibility_bidirectional', '__return_false' );

		wc_get_product( $a )->delete( true );

		$this->assertSame( array(), Compatibility::stored_ids( $b ) );
		$this->assertSame( array( $d ), Compatibility::stored_ids( $c ), 'One-way references are cleaned too.' );
	}

	public function test_trashing_keeps_links(): void {
		$a = $this->create_product()->get_id();
		$b = $this->create_product()->get_id();
		Compatibility::save( $a, array( $b ) );

		wp_trash_post( $a );

		$this->assertSame( array( $a ), Compatibility::stored_ids( $b ) );
	}

	public function test_two_way_add_and_remove(): void {
		$a = $this->create_product()->get_id();
		$b = $this->create_product()->get_id();
		$c = $this->create_product()->get_id();

		Compatibility::save( $a, array( $b, $c, $a, $b, 0 ) );
		$this->assertSame( array( $b, $c ), Compatibility::stored_ids( $a ) );
		$this->assertSame( array( $a ), Compatibility::stored_ids( $b ) );
		$this->assertSame( array( $a ), Compatibility::stored_ids( $c ) );

		Compatibility::save( $a, array( $c ) );
		$this->assertSame( array( $c ), Compatibility::stored_ids( $a ) );
		$this->assertSame( array(), Compatibility::stored_ids( $b ) );
		$this->assertFalse( metadata_exists( 'post', $b, Compatibility::META ) );
		$this->assertSame( array( $a ), Compatibility::stored_ids( $c ) );
	}

	public function test_mirroring_keeps_other_links_of_the_linked_product(): void {
		$a = $this->create_product()->get_id();
		$b = $this->create_product()->get_id();
		$x = $this->create_product()->get_id();
		Compatibility::save( $b, array( $x ) );

		Compatibility::save( $a, array( $b ) );

		$this->assertEqualsCanonicalizing( array( $x, $a ), Compatibility::stored_ids( $b ) );
	}

	public function test_bidirectional_filter_disables_mirroring(): void {
		$a = $this->create_product()->get_id();
		$b = $this->create_product()->get_id();
		add_filter( 'lpg_compatibility_bidirectional', '__return_false' );

		Compatibility::save( $a, array( $b ) );

		$this->assertSame( array( $b ), Compatibility::stored_ids( $a ) );
		$this->assertSame( array(), Compatibility::stored_ids( $b ) );
	}

	public function test_rejects_non_products(): void {
		$a    = $this->create_product()->get_id();
		$post = self::factory()->post->create();

		Compatibility::save( $a, array( $post, 999999 ) );

		$this->assertSame( array(), Compatibility::stored_ids( $a ) );
	}

	public function test_get_ids_returns_published_visible_only(): void {
		$a       = $this->create_product()->get_id();
		$visible = $this->create_product()->get_id();
		$draft   = $this->create_product();
		$draft->set_status( 'draft' );
		$draft->save();
		$hidden = $this->create_product();
		$hidden->set_catalog_visibility( 'hidden' );
		$hidden->save();

		Compatibility::save( $a, array( $visible, $draft->get_id(), $hidden->get_id() ) );

		$this->assertCount( 3, Compatibility::stored_ids( $a ) );
		$this->assertSame( array( $visible ), Compatibility::get_ids( $a ) );
	}

	public function test_save_through_process_product_meta_hook(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$a = $this->create_product()->get_id();
		$b = $this->create_product()->get_id();

		$_POST = array(
			'lpg_compatibility_nonce' => wp_create_nonce( 'lpg_save_compatibility' ),
			'lpg_compatible_ids'      => array( (string) $b ),
		);
		do_action( 'woocommerce_process_product_meta', $a, get_post( $a ) );
		$_POST = array();

		$this->assertSame( array( $b ), Compatibility::stored_ids( $a ) );
		$this->assertSame( array( $a ), Compatibility::stored_ids( $b ) );
	}
}
