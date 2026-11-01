<?php
/**
 * Installer additions, seed command and registrations (Phase 3A).
 *
 * @package LPG\Commerce\Tests
 */

use LPG\Commerce\Cli\SeedCommand;
use LPG\Commerce\Installer;
use LPG\Commerce\Specs\ProfilePostType;
use LPG\Commerce\Specs\ProfileRepository;

class CatalogueSetupTest extends WP_UnitTestCase {

	public function test_segment_taxonomy_registered_per_contract(): void {
		$taxonomy = get_taxonomy( 'lpg_segment' );

		$this->assertInstanceOf( WP_Taxonomy::class, $taxonomy );
		$this->assertFalse( $taxonomy->hierarchical );
		$this->assertTrue( $taxonomy->public );
		$this->assertTrue( $taxonomy->show_in_rest );
		$this->assertSame( 'segment', $taxonomy->rewrite['slug'] );
		$this->assertSame( 'assign_product_terms', $taxonomy->cap->assign_terms );
		$this->assertContains( 'product', $taxonomy->object_type );
	}

	public function test_install_seeds_three_segments_and_never_overwrites(): void {
		Installer::install();
		$term = get_term_by( 'slug', 'household', 'lpg_segment' );
		wp_update_term( $term->term_id, 'lpg_segment', array( 'name' => 'Homes' ) );

		Installer::install();

		$terms = get_terms(
			array(
				'taxonomy'   => 'lpg_segment',
				'hide_empty' => false,
				'fields'     => 'id=>slug',
			)
		);
		$this->assertEqualsCanonicalizing( array( 'household', 'commercial', 'industrial' ), array_values( $terms ) );
		$this->assertSame( 'Homes', get_term_by( 'slug', 'household', 'lpg_segment' )->name );
	}

	public function test_caps_granted_to_shop_manager_and_administrator(): void {
		Installer::activate();

		foreach ( array( 'shop_manager', 'administrator' ) as $role ) {
			foreach ( ProfilePostType::CAPS as $cap ) {
				$this->assertTrue( get_role( $role )->has_cap( $cap ), "$role should have $cap" );
			}
		}

		$manager = self::factory()->user->create( array( 'role' => 'shop_manager' ) );
		$editor  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$profile = self::factory()->post->create( array( 'post_type' => ProfilePostType::POST_TYPE ) );

		$this->assertTrue( user_can( $manager, 'edit_post', $profile ) );
		$this->assertTrue( user_can( $manager, 'delete_post', $profile ) );
		$this->assertFalse( user_can( $editor, 'edit_post', $profile ) );
	}

	public function test_first_install_grants_caps_but_upgrade_does_not_regrant(): void {
		$role = get_role( 'shop_manager' );
		foreach ( ProfilePostType::CAPS as $cap ) {
			$role->remove_cap( $cap );
		}

		update_option( Installer::VERSION_OPTION, '0.1.0', false );
		Installer::install();
		$this->assertFalse( get_role( 'shop_manager' )->has_cap( 'edit_lpg_spec_profiles' ), 'Upgrade must not re-grant removed caps.' );
		$this->assertSame( LPG_COMMERCE_VERSION, get_option( Installer::VERSION_OPTION ) );

		delete_option( Installer::VERSION_OPTION );
		Installer::install();
		$this->assertTrue( get_role( 'shop_manager' )->has_cap( 'edit_lpg_spec_profiles' ), 'First install grants caps.' );
	}

	public function test_profile_post_type_is_private(): void {
		$type = get_post_type_object( ProfilePostType::POST_TYPE );

		$this->assertFalse( $type->public );
		$this->assertTrue( $type->show_ui );
		$this->assertFalse( $type->show_in_rest );
		$this->assertSame( 'edit_lpg_spec_profiles', $type->cap->edit_posts );
	}

	public function test_seed_is_idempotent(): void {
		$first = SeedCommand::run();

		$this->assertSame( 4, $first['profiles_created'] );
		$this->assertSame( 11, $first['categories_created'] );
		$this->assertSame( 4, $first['assignments_made'] );
		$this->assertSame( array(), $first['errors'] );

		$counts = $this->counts();
		$second = SeedCommand::run();

		$this->assertSame( 0, $second['profiles_created'] );
		$this->assertSame( 4, $second['profiles_existing'] );
		$this->assertSame( 0, $second['categories_created'] );
		$this->assertSame( 11, $second['categories_existing'] );
		$this->assertSame( 0, $second['assignments_made'] );
		$this->assertSame( 4, $second['assignments_kept'] );
		$this->assertSame( $counts, $this->counts() );
	}

	public function test_seed_creates_definitions_only_and_maps_categories(): void {
		SeedCommand::run();

		$regulators = get_term_by( 'slug', 'regulators', 'product_cat' );
		$profile_id = (int) get_term_meta( $regulators->term_id, 'lpg_spec_profile_id', true );
		$this->assertSame( 'Regulator', get_the_title( $profile_id ) );

		$keys = wp_list_pluck( ProfileRepository::get_fields( $profile_id ), 'key' );
		$this->assertSame( array( 'regulator_type', 'operating_pressure', 'outlet_pressure', 'flow_rate', 'inlet_connection', 'outlet_connection', 'compatible_applications' ), $keys );
		$this->assertGreaterThan( 0, wc_attribute_taxonomy_id_by_name( 'outlet_pressure' ) );
		$this->assertSame( 0, (int) wp_count_posts( 'product' )->publish );
	}

	public function test_seed_never_overwrites_existing_assignment_or_profile(): void {
		$custom = self::factory()->post->create(
			array(
				'post_type'  => ProfilePostType::POST_TYPE,
				'post_title' => 'My burners',
			)
		);
		$term   = wp_insert_term( 'Burners', 'product_cat', array( 'slug' => 'burners' ) );
		update_term_meta( $term['term_id'], 'lpg_spec_profile_id', $custom );

		$existing = self::factory()->post->create(
			array(
				'post_type'  => ProfilePostType::POST_TYPE,
				'post_title' => 'Cylinder',
				'post_name'  => 'cylinder',
			)
		);
		update_post_meta(
			$existing,
			'_lpg_fields',
			array(
				array(
					'key'   => 'mine',
					'label' => 'Mine',
					'type'  => 'text',
				),
			)
		);

		$summary = SeedCommand::run();

		$this->assertSame( $custom, (int) get_term_meta( $term['term_id'], 'lpg_spec_profile_id', true ) );
		$this->assertSame( 3, $summary['profiles_created'] );
		$this->assertSame( array( 'mine' ), wp_list_pluck( ProfileRepository::get_fields( $existing ), 'key' ) );
	}

	private function counts(): array {
		return array(
			'profiles'   => count(
				get_posts(
					array(
						'post_type'   => ProfilePostType::POST_TYPE,
						'post_status' => 'any',
						'numberposts' => -1,
					)
				)
			),
			'categories' => (int) wp_count_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
				)
			),
		);
	}
}
