<?php
/**
 * Authorisation: segment terms (CAT-007), spec profiles (SEC-001) and product
 * LPG meta saves by users who cannot edit the product (CAT-049 / SEC-003).
 *
 * Uses real roles, real nonces and the plugin's real save hooks.
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgFixtures.php';

use LPG\Commerce\Commerce\ProductFields;
use LPG\Commerce\Relations\Compatibility;
use LPG\Commerce\Relations\Documents;
use LPG\Commerce\Specs\ProfilePostType;
use LPG\Commerce\Specs\ProfileRepository;
use LPG\Commerce\Taxonomies\Segment;

class PermissionsTest extends WP_UnitTestCase {

	use LpgFixtures;

	/**
	 * Roles that must not manage segments or spec profiles.
	 */
	private const LOW_ROLES = array( 'subscriber', 'customer', 'contributor', 'author' );

	public function set_up(): void {
		parent::set_up();
		Segment::seed_terms();
	}

	public function tear_down(): void {
		$_POST = array();
		remove_role( 'lpg_test_own_products' );
		remove_role( 'lpg_test_no_assign' );
		parent::tear_down();
	}

	private function user( string $role ): int {
		return self::factory()->user->create( array( 'role' => $role ) );
	}

	private function household_id(): int {
		return (int) get_term_by( 'slug', 'household', Segment::TAXONOMY )->term_id;
	}

	// ----- CAT-007: lpg_segment term capabilities ------------------------------------

	public function test_low_roles_cannot_manage_or_assign_segment_terms(): void {
		$household = $this->household_id();

		foreach ( self::LOW_ROLES as $role ) {
			wp_set_current_user( $this->user( $role ) );
			// phpcs:disable WordPress.WP.Capabilities.Unknown -- WooCommerce caps.
			$this->assertFalse( current_user_can( 'manage_product_terms' ), $role );
			$this->assertFalse( current_user_can( 'edit_product_terms' ), $role );
			$this->assertFalse( current_user_can( 'delete_product_terms' ), $role );
			$this->assertFalse( current_user_can( 'assign_product_terms' ), $role );
			// phpcs:enable
			$this->assertFalse( current_user_can( 'assign_term', $household ), $role . ' assign_term' );
			$this->assertFalse( current_user_can( 'edit_term', $household ), $role . ' edit_term' );
			$this->assertFalse( current_user_can( 'delete_term', $household ), $role . ' delete_term' );
		}

		// Author has edit_posts but that is not enough.
		$this->assertTrue( current_user_can( 'edit_posts' ) );
	}

	public function test_low_roles_cannot_create_or_rename_segments_through_rest(): void {
		$household = $this->household_id();

		foreach ( array_merge( array( 'guest' ), self::LOW_ROLES ) as $role ) {
			wp_set_current_user( 'guest' === $role ? 0 : $this->user( $role ) );

			$create = new WP_REST_Request( 'POST', '/wp/v2/lpg_segment' );
			$create->set_param( 'name', 'QA hostile ' . $role );
			$response = rest_do_request( $create );
			$this->assertSame( 'guest' === $role ? 401 : 403, $response->get_status(), $role . ' create' );
			$this->assertSame( 'rest_cannot_create', $response->as_error()->get_error_code(), $role );
			$this->assertEmpty( term_exists( 'QA hostile ' . $role, Segment::TAXONOMY ), $role );

			$update = new WP_REST_Request( 'POST', '/wp/v2/lpg_segment/' . $household );
			$update->set_param( 'name', 'Renamed by ' . $role );
			$response = rest_do_request( $update );
			$this->assertContains( $response->get_status(), array( 401, 403 ), $role . ' update' );
			$this->assertSame( 'Household', get_term( $household, Segment::TAXONOMY )->name, $role );

			$delete = new WP_REST_Request( 'DELETE', '/wp/v2/lpg_segment/' . $household );
			$delete->set_param( 'force', true );
			$this->assertContains( rest_do_request( $delete )->get_status(), array( 401, 403 ), $role . ' delete' );
			$this->assertInstanceOf( WP_Term::class, get_term( $household, Segment::TAXONOMY ), $role );
		}
	}

	public function test_shop_manager_and_administrator_can_manage_segments_through_rest(): void {
		foreach ( array( 'shop_manager', 'administrator' ) as $role ) {
			wp_set_current_user( $this->user( $role ) );
			$this->assertTrue( current_user_can( 'assign_term', $this->household_id() ), $role );

			$request = new WP_REST_Request( 'POST', '/wp/v2/lpg_segment' );
			$request->set_param( 'name', 'QA segment ' . $role );
			$this->assertSame( 201, rest_do_request( $request )->get_status(), $role );
		}
	}

	public function test_author_cannot_assign_segment_to_a_product_through_rest(): void {
		$product = $this->create_product()->get_id();
		wp_set_current_user( $this->user( 'author' ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/product/' . $product );
		$request->set_param( Segment::TAXONOMY, array( $this->household_id() ) );
		$response = rest_do_request( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( array(), wp_get_object_terms( $product, Segment::TAXONOMY, array( 'fields' => 'ids' ) ) );
	}

	public function test_product_editor_without_assign_product_terms_cannot_assign_segment(): void {
		// Can edit every product but lacks assign_product_terms: isolates the
		// taxonomy's assign_terms mapping from the post edit check.
		add_role(
			'lpg_test_no_assign',
			'Edits products, cannot assign terms',
			array(
				'read'                    => true,
				'edit_products'           => true,
				'edit_others_products'    => true,
				'edit_published_products' => true,
				'publish_products'        => true,
			)
		);
		$product = $this->create_product()->get_id();
		$user    = $this->user( 'lpg_test_no_assign' );
		wp_set_current_user( $user );
		$this->assertTrue( current_user_can( 'edit_post', $product ) );
		$this->assertFalse( current_user_can( 'assign_term', $this->household_id() ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/product/' . $product );
		$request->set_param( Segment::TAXONOMY, array( $this->household_id() ) );
		$response = rest_do_request( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'rest_cannot_assign_term', $response->as_error()->get_error_code() );
		$this->assertSame( array(), wp_get_object_terms( $product, Segment::TAXONOMY, array( 'fields' => 'ids' ) ) );

		// Control: a shop manager can assign it through the same route.
		wp_set_current_user( $this->user( 'shop_manager' ) );
		$this->assertSame( 200, rest_do_request( $request )->get_status() );
		$this->assertSame( array( $this->household_id() ), wp_get_object_terms( $product, Segment::TAXONOMY, array( 'fields' => 'ids' ) ) );
	}

	// ----- SEC-001: spec profile screens ----------------------------------------------

	private function require_admin_post_functions(): void {
		require_once ABSPATH . 'wp-admin/includes/post.php';
	}

	public function test_low_roles_and_guest_are_denied_the_profile_screens_and_edits(): void {
		$this->require_admin_post_functions();
		$fields  = array( $this->field( 'capacity', 'number', 'kg' ) );
		$profile = $this->create_profile( $fields, 'publish', 'QA profile' );
		$before  = get_post_meta( $profile, ProfileRepository::FIELDS_META, true );
		$pto     = get_post_type_object( ProfilePostType::POST_TYPE );
		$handler = new ProfilePostType();

		foreach ( array_merge( array( 'guest' ), self::LOW_ROLES ) as $role ) {
			wp_set_current_user( 'guest' === $role ? 0 : $this->user( $role ) );

			// edit.php?post_type=lpg_spec_profile and post-new.php gate on these.
			$this->assertFalse( current_user_can( $pto->cap->edit_posts ), $role . ' list screen' ); // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Post type cap.
			$this->assertFalse( current_user_can( $pto->cap->create_posts ), $role . ' add new' ); // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Post type cap.
			$this->assertFalse( current_user_can( 'edit_post', $profile ), $role . ' edit' );
			$this->assertFalse( current_user_can( 'delete_post', $profile ), $role . ' delete' );

			// post.php?action=editpost runs core edit_post(), which must wp_die.
			$_POST = array(
				'post_ID'    => $profile,
				'post_title' => 'Renamed by ' . $role,
			);
			try {
				edit_post();
				$this->fail( $role . ': edit_post() did not die.' );
			} catch ( WPDieException $e ) {
				$this->assertStringContainsString( 'not allowed to edit', $e->getMessage() );
			}
			$this->assertSame( 'QA profile', get_post( $profile )->post_title, $role );

			// The plugin's field save handler, even with a valid nonce for this user.
			$_POST = array(
				'lpg_spec_profile_nonce' => wp_create_nonce( 'lpg_save_spec_profile' ),
				'lpg_fields'             => array( $this->field( 'hacked' ) ),
			);
			$handler->handle_save( $profile, get_post( $profile ) );
			$_POST = array();
			$this->assertSame( $before, get_post_meta( $profile, ProfileRepository::FIELDS_META, true ), $role . ' fields unchanged' );
		}
	}

	public function test_profile_post_type_has_no_rest_route_for_anyone(): void {
		$this->create_profile( array( $this->field( 'capacity' ) ) );
		foreach ( array( 'guest', 'author', 'administrator' ) as $role ) {
			wp_set_current_user( 'guest' === $role ? 0 : $this->user( $role ) );
			$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/' . ProfilePostType::POST_TYPE ) );
			$this->assertSame( 404, $response->get_status(), $role );
			$this->assertSame( 'rest_no_route', $response->as_error()->get_error_code(), $role );
		}
	}

	public function test_shop_manager_can_edit_profile_fields_through_the_save_handler(): void {
		$profile = $this->create_profile( array( $this->field( 'capacity' ) ) );
		wp_set_current_user( $this->user( 'shop_manager' ) );
		$this->assertTrue( current_user_can( get_post_type_object( ProfilePostType::POST_TYPE )->cap->edit_posts ) ); // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Post type cap.

		$_POST = array(
			'lpg_spec_profile_nonce' => wp_create_nonce( 'lpg_save_spec_profile' ),
			'lpg_fields'             => array( $this->field( 'material' ) ),
		);
		( new ProfilePostType() )->handle_save( $profile, get_post( $profile ) );

		$this->assertSame( array( 'material' ), wp_list_pluck( ProfileRepository::get_fields( $profile ), 'key' ) );
	}

	// ----- CAT-049 / SEC-003: product LPG meta without edit_post -----------------------

	/**
	 * A user with edit_products who may edit only their own products.
	 */
	private function own_products_user(): int {
		add_role(
			'lpg_test_own_products',
			'Own products only',
			array(
				'read'                    => true,
				'edit_products'           => true,
				'edit_published_products' => true,
				'upload_files'            => true,
			)
		);

		return $this->user( 'lpg_test_own_products' );
	}

	/**
	 * Product resolving to a profile with one text and one number field.
	 *
	 * @return array{0: WC_Product_Simple, 1: int} Product and profile ID.
	 */
	private function product_with_profile( int $author = 0 ): array {
		$profile = $this->create_profile( array( $this->field( 'capacity', 'number', 'kg' ), $this->field( 'material' ) ) );
		$product = $this->create_product( array( $this->create_category( 0, $profile ) ) );
		if ( $author ) {
			wp_update_post(
				array(
					'ID'          => $product->get_id(),
					'post_author' => $author,
				)
			);
			$product = wc_get_product( $product->get_id() );
		}

		return array( $product, $profile );
	}

	/**
	 * Submit every LPG product form (specs, warranty/enquiry-only, documents,
	 * compatibility) through the real hooks with valid nonces for the current user.
	 */
	private function submit_all_lpg_forms( WC_Product $product, int $profile, int $pdf, int $other ): void {
		$_POST = array(
			'lpg_product_specs_nonce'   => wp_create_nonce( 'lpg_save_product_specs' ),
			'_lpg_spec_profile_id'      => '0',
			'lpg_spec_rendered_profile' => (string) $profile,
			'lpg_spec'                  => array(
				'capacity' => '13',
				'material' => 'Brass',
			),
			'lpg_product_fields_nonce'  => wp_create_nonce( 'lpg_save_product_fields' ),
			'_lpg_warranty'             => 'Forged warranty',
			'_lpg_enquiry_only'         => 'yes',
			'lpg_documents_nonce'       => wp_create_nonce( 'lpg_save_documents' ),
			'lpg_documents'             => array(
				array(
					'attachment_id' => (string) $pdf,
					'type'          => 'datasheet',
					'label'         => 'Forged',
				),
			),
			'lpg_compatibility_nonce'   => wp_create_nonce( 'lpg_save_compatibility' ),
			'lpg_compatible_ids'        => array( (string) $other ),
		);
		do_action( 'woocommerce_admin_process_product_object', $product );
		$product->save();
		do_action( 'woocommerce_process_product_meta', $product->get_id(), get_post( $product->get_id() ) );
		$_POST = array();
	}

	private function pdf(): int {
		return self::factory()->attachment->create_object(
			array(
				'file'           => 'qa-sec-003.pdf',
				'post_mime_type' => 'application/pdf',
				'post_title'     => 'qa-sec-003.pdf',
			)
		);
	}

	/**
	 * LPG meta keys a forged save would write.
	 *
	 * @return string[]
	 */
	private function lpg_keys(): array {
		return array(
			'_lpg_spec_capacity',
			'_lpg_spec_material',
			ProductFields::WARRANTY_META,
			ProductFields::ENQUIRY_META,
			Documents::META,
			Compatibility::META,
		);
	}

	public function test_user_without_edit_post_on_product_saves_no_lpg_meta(): void {
		$user                  = $this->own_products_user();
		[ $product, $profile ] = $this->product_with_profile();
		$other                 = $this->create_product()->get_id();
		$pdf                   = $this->pdf();
		$product_id            = $product->get_id();

		wp_set_current_user( $user );
		$this->assertTrue( current_user_can( 'edit_products' ) ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce cap.
		$this->assertFalse( current_user_can( 'edit_post', $product_id ), 'Precondition: not this product.' );

		$this->submit_all_lpg_forms( $product, $profile, $pdf, $other );

		foreach ( $this->lpg_keys() as $key ) {
			$this->assertFalse( metadata_exists( 'post', $product_id, $key ), $key . ' must not be saved.' );
		}
		$this->assertFalse( metadata_exists( 'post', $other, Compatibility::META ), 'No mirror written.' );
		$this->assertTrue( wc_get_product( $product_id )->is_purchasable() );
	}

	public function test_low_roles_save_no_lpg_meta(): void {
		[ $product, $profile ] = $this->product_with_profile();
		$other                 = $this->create_product()->get_id();
		$pdf                   = $this->pdf();

		foreach ( self::LOW_ROLES as $role ) {
			wp_set_current_user( $this->user( $role ) );
			$this->assertFalse( current_user_can( 'edit_post', $product->get_id() ), $role );

			$this->submit_all_lpg_forms( wc_get_product( $product->get_id() ), $profile, $pdf, $other );

			foreach ( $this->lpg_keys() as $key ) {
				$this->assertFalse( metadata_exists( 'post', $product->get_id(), $key ), $role . ': ' . $key );
			}
		}
	}

	public function test_same_user_on_own_product_saves_lpg_meta(): void {
		// Control for the two tests above: the same forms and nonces do save
		// when the user may edit the product, so the denial is the capability check.
		$user                  = $this->own_products_user();
		[ $product, $profile ] = $this->product_with_profile( $user );
		$other                 = $this->create_product();
		wp_update_post(
			array(
				'ID'          => $other->get_id(),
				'post_author' => $user,
			)
		);
		$pdf = $this->pdf();

		wp_set_current_user( $user );
		$this->assertTrue( current_user_can( 'edit_post', $product->get_id() ) );

		$this->submit_all_lpg_forms( $product, $profile, $pdf, $other->get_id() );

		$id = $product->get_id();
		$this->assertSame( '13', get_post_meta( $id, '_lpg_spec_capacity', true ) );
		$this->assertSame( 'Brass', get_post_meta( $id, '_lpg_spec_material', true ) );
		$this->assertSame( 'Forged warranty', get_post_meta( $id, ProductFields::WARRANTY_META, true ) );
		$this->assertSame( 'yes', get_post_meta( $id, ProductFields::ENQUIRY_META, true ) );
		$this->assertSame( array( $pdf ), wp_list_pluck( Documents::get( $id ), 'attachment_id' ) );
		$this->assertSame( array( $other->get_id() ), Compatibility::stored_ids( $id ) );
	}

	public function test_woocommerce_rest_product_update_denied_without_edit_post(): void {
		$user       = $this->own_products_user();
		$product_id = $this->create_product()->get_id();
		wp_set_current_user( $user );

		$request = new WP_REST_Request( 'PUT', '/wc/v3/products/' . $product_id );
		$request->set_body_params(
			array(
				'meta_data' => array(
					array(
						'key'   => '_lpg_warranty',
						'value' => '<script>alert(1)</script>',
					),
					array(
						'key'   => '_lpg_enquiry_only',
						'value' => 'yes',
					),
				),
			)
		);
		$response = rest_do_request( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'woocommerce_rest_cannot_edit', $response->as_error()->get_error_code() );
		$this->assertFalse( metadata_exists( 'post', $product_id, ProductFields::WARRANTY_META ) );
		$this->assertFalse( metadata_exists( 'post', $product_id, ProductFields::ENQUIRY_META ) );
	}
}
