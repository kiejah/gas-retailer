<?php
/**
 * Spec profile resolution (§11.3, C2).
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgFixtures.php';

use LPG\Commerce\Specs\ProfileResolver;

class ProfileResolverTest extends WP_UnitTestCase {

	use LpgFixtures;

	public function test_override_wins_over_category(): void {
		$cat_profile      = $this->create_profile( array( $this->field( 'a' ) ) );
		$override_profile = $this->create_profile( array( $this->field( 'b' ) ) );
		$cat              = $this->create_category( 0, $cat_profile );
		$product          = $this->create_product( array( $cat ), array( '_lpg_spec_profile_id' => $override_profile ) );

		$this->assertSame( $override_profile, ProfileResolver::for_product( $product->get_id() ) );
	}

	public function test_deepest_category_wins(): void {
		$top_profile   = $this->create_profile( array( $this->field( 'a' ) ) );
		$child_profile = $this->create_profile( array( $this->field( 'b' ) ) );
		$top           = $this->create_category( 0, $top_profile );
		$child         = $this->create_category( $top, $child_profile );
		$other_top     = $this->create_category( 0, $top_profile );

		$product = $this->create_product( array( $other_top, $child ) );

		$this->assertSame( $child_profile, ProfileResolver::for_product( $product->get_id() ) );
	}

	public function test_walks_up_ancestors(): void {
		$profile    = $this->create_profile( array( $this->field( 'a' ) ) );
		$top        = $this->create_category( 0, $profile );
		$child      = $this->create_category( $top );
		$grandchild = $this->create_category( $child );

		$product = $this->create_product( array( $grandchild ) );

		$this->assertSame( $profile, ProfileResolver::for_product( $product->get_id() ) );
	}

	public function test_same_depth_uses_lowest_term_id(): void {
		$first_profile  = $this->create_profile( array( $this->field( 'a' ) ) );
		$second_profile = $this->create_profile( array( $this->field( 'b' ) ) );
		$first          = $this->create_category( 0, $first_profile );
		$second         = $this->create_category( 0, $second_profile );
		$this->assertLessThan( $second, $first );

		$product = $this->create_product( array( $second, $first ) );

		$this->assertSame( $first_profile, ProfileResolver::for_product( $product->get_id() ) );
	}

	public function test_no_profile_returns_null(): void {
		$product = $this->create_product( array( $this->create_category() ) );

		$this->assertNull( ProfileResolver::for_product( $product->get_id() ) );
		$this->assertNull( ProfileResolver::for_product( 0 ) );
	}

	public function test_invalid_override_falls_back_to_category(): void {
		$profile = $this->create_profile( array( $this->field( 'a' ) ) );
		$cat     = $this->create_category( 0, $profile );
		$trashed = $this->create_profile( array( $this->field( 'b' ) ) );
		wp_trash_post( $trashed );
		$wrong_type = self::factory()->post->create();

		foreach ( array( $trashed, $wrong_type, 999999 ) as $override ) {
			$product = $this->create_product( array( $cat ), array( '_lpg_spec_profile_id' => $override ) );
			$this->assertSame( $profile, ProfileResolver::for_product( $product->get_id() ), "Override $override should be ignored." );
		}
	}

	public function test_trashed_category_profile_is_skipped_and_walk_continues(): void {
		$profile = $this->create_profile( array( $this->field( 'a' ) ) );
		$trashed = $this->create_profile( array( $this->field( 'b' ) ) );
		wp_trash_post( $trashed );
		$top   = $this->create_category( 0, $profile );
		$child = $this->create_category( $top, $trashed );

		$product = $this->create_product( array( $child ) );

		$this->assertSame( $profile, ProfileResolver::for_product( $product->get_id() ) );
	}
}
