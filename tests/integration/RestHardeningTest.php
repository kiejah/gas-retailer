<?php
/**
 * REST hardening tests.
 *
 * @package LPG\Commerce\Tests
 */

/**
 * Tests for LPG\Commerce\Security\RestHardening (§13.10).
 */
class RestHardeningTest extends WP_UnitTestCase {

	private static int $author;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$author = $factory->user->create( array( 'role' => 'author' ) );
		$factory->post->create(
			array(
				'post_author' => self::$author,
				'post_status' => 'publish',
			)
		);
	}

	public function set_up(): void {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		do_action( 'rest_api_init', $wp_rest_server );
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		parent::tear_down();
	}

	private function get( string $route ): WP_REST_Response {
		return rest_do_request( new WP_REST_Request( 'GET', $route ) );
	}

	public function test_anonymous_user_list_and_single_user_get_401(): void {
		wp_set_current_user( 0 );

		foreach ( array( '/wp/v2/users', '/wp/v2/users/' . self::$author, '/wp/v2/USERS', '/wp/v2/users/' ) as $route ) {
			$response = $this->get( $route );
			$this->assertSame( 401, $response->get_status(), $route );
			$this->assertSame( 'rest_forbidden', $response->get_data()['code'], $route );
		}
	}

	public function test_admin_can_list_users(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( 200, $this->get( '/wp/v2/users' )->get_status() );
		$this->assertSame( 200, $this->get( '/wp/v2/users/' . self::$author )->get_status() );
	}

	public function test_public_routes_still_work_anonymously(): void {
		wp_set_current_user( 0 );

		$this->assertSame( 200, $this->get( '/wp/v2/posts' )->get_status() );
		$this->assertSame( 200, $this->get( '/' )->get_status() );
		// Neighbouring routes are not matched by the users rule.
		$this->assertSame( 401, $this->get( '/wp/v2/users/me' )->get_status() );
		$this->assertSame( 'rest_not_logged_in', $this->get( '/wp/v2/users/me' )->get_data()['code'] );
	}
}
