<?php
/**
 * CAT-020: hostile spec-profile labels are stored sanitised and rendered escaped,
 * in the admin (profile meta box, notices, product panel) and on the front end.
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgFixtures.php';

use LPG\Commerce\Blocks\SpecTable;
use LPG\Commerce\Specs\ProductSpecsPanel;
use LPG\Commerce\Specs\ProfilePostType;
use LPG\Commerce\Specs\ProfileRepository;

class ProfileLabelEscapingTest extends WP_UnitTestCase {

	use LpgFixtures;

	private const SCRIPT = '<script>alert(1)</script>';

	private const BREAKOUT = 'Cap"><img src=x onerror=alert(1)>';

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		$_POST = array();
		$_GET  = array();
		unset( $GLOBALS['post'] );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * Fail on anything that would execute or break out of an attribute.
	 */
	private function assert_no_raw_injection( string $html ): void {
		$this->assertStringNotContainsString( '<script>alert', $html );
		$this->assertStringNotContainsString( '<img', $html );

		// Parse it as a browser would: no injected elements or event-handler attributes.
		$dom      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$dom->loadHTML( '<!DOCTYPE html><html><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		$xpath = new DOMXPath( $dom );
		$this->assertSame( 0, $xpath->query( '//script | //img' )->length, 'Injected element.' );
		$this->assertSame( 0, $xpath->query( '//@*[starts-with(translate(name(), "ON", "on"), "on")]' )->length, 'Event-handler attribute.' );
	}

	private function render_profile_box( int $profile ): string {
		ob_start();
		( new ProfilePostType() )->render_meta_box( get_post( $profile ) );

		return (string) ob_get_clean();
	}

	public function test_script_label_saved_through_admin_handler_is_stored_sanitised(): void {
		$profile = $this->create_profile( array() );

		$_POST = array(
			'lpg_spec_profile_nonce' => wp_create_nonce( 'lpg_save_spec_profile' ),
			'lpg_fields'             => array(
				array_merge( $this->field( 'colour' ), array( 'label' => self::SCRIPT ) ),
				array_merge(
					$this->field( 'material' ),
					array(
						'label' => self::BREAKOUT . ' <b>Bold</b>',
						'unit'  => '<i>kg</i>',
						'help'  => self::SCRIPT . 'Help',
					)
				),
			),
		);
		( new ProfilePostType() )->handle_save( $profile, get_post( $profile ) );
		$_POST = array();

		$stored = get_post_meta( $profile, ProfileRepository::FIELDS_META, true );
		$this->assertSame( array( 'material' ), wp_list_pluck( $stored, 'key' ), 'A label that sanitises to empty is rejected (label required).' );
		$this->assertSame( 'Cap"> Bold', $stored[0]['label'], 'Tags stripped by sanitize_text_field.' );
		$this->assertSame( 'kg', $stored[0]['unit'] );
		$this->assertSame( 'Help', $stored[0]['help'] );
		$this->assertStringNotContainsString( '<', wp_json_encode( $stored ) );

		// The rejected row is reported in a notice, also escaped.
		$notice = get_transient( 'lpg_profile_notice_' . get_current_user_id() . '_' . $profile );
		$this->assertIsArray( $notice );
		$this->assertStringContainsString( 'label is required', implode( ' ', $notice ) );

		$html = $this->render_profile_box( $profile );
		$this->assert_no_raw_injection( $html );
		$this->assertStringContainsString( 'value="Cap&quot;&gt; Bold"', $html );
	}

	public function test_hostile_raw_meta_is_escaped_in_profile_meta_box(): void {
		// Simulates data written without the admin handler (e.g. a REST or import path).
		$profile = self::factory()->post->create( array( 'post_type' => ProfilePostType::POST_TYPE ) );
		update_post_meta(
			$profile,
			ProfileRepository::FIELDS_META,
			array(
				array(
					'key'        => 'valve',
					'label'      => self::BREAKOUT . self::SCRIPT,
					'type'       => 'select',
					'unit'       => '"><script>alert(2)</script>',
					'options'    => array( 'x" onmouseover="alert(1)', '</textarea><script>alert(3)</script>' ),
					'filterable' => false,
					'help'       => '" onmouseover="alert(4)',
				),
			)
		);

		$html = $this->render_profile_box( $profile );

		$this->assert_no_raw_injection( $html );
		$this->assertStringNotContainsString( '</textarea><script>', $html );
		$this->assertStringContainsString( 'value="Cap&quot;&gt;"', $html, 'Label escaped inside the attribute.' );
		$this->assertStringContainsString( 'x&quot; onmouseover=&quot;alert(1)', $html, 'Option escaped inside the textarea.' );
		$this->assertStringContainsString( 'value="&quot; onmouseover=&quot;alert(4)"', $html, 'Help escaped.' );
	}

	public function test_rejected_row_notice_is_escaped(): void {
		$profile = $this->create_profile( array() );
		$_POST   = array(
			'lpg_spec_profile_nonce' => wp_create_nonce( 'lpg_save_spec_profile' ),
			'lpg_fields'             => array(
				array_merge( $this->field( 'ok' ), array( 'key' => 'bad"><img src=x onerror=alert(1)>' ) ),
			),
		);
		( new ProfilePostType() )->handle_save( $profile, get_post( $profile ) );
		$_POST = array();

		set_current_screen( ProfilePostType::POST_TYPE );
		$_GET['post'] = (string) $profile;
		ob_start();
		( new ProfilePostType() )->render_notices();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $html );
		$this->assert_no_raw_injection( $html );
		$this->assertStringContainsString( 'bad&quot;&gt;', $html );
	}

	public function test_hostile_labels_and_values_are_escaped_in_product_panel_and_front_end(): void {
		if ( ! function_exists( 'woocommerce_wp_text_input' ) ) {
			require_once WC_ABSPATH . 'includes/admin/wc-meta-box-functions.php';
		}
		$profile = self::factory()->post->create(
			array(
				'post_type'  => ProfilePostType::POST_TYPE,
				'post_title' => 'QA <script>alert(5)</script> profile',
			)
		);
		update_post_meta(
			$profile,
			ProfileRepository::FIELDS_META,
			array(
				array(
					'key'        => 'colour',
					'label'      => self::BREAKOUT . self::SCRIPT,
					'type'       => 'text',
					'unit'       => '',
					'options'    => array(),
					'filterable' => false,
					'help'       => '"><script>alert(6)</script>',
				),
				array(
					'key'        => 'valve',
					'label'      => 'Valve',
					'type'       => 'select',
					'unit'       => '',
					'options'    => array( 'Clip-on' ),
					'filterable' => false,
					'help'       => '',
				),
			)
		);
		$product = $this->create_product();
		$id      = $product->get_id();
		update_post_meta( $id, '_lpg_spec_profile_id', $profile );
		update_post_meta( $id, '_lpg_spec_colour', 'Red" onmouseover="alert(7)' );
		update_post_meta( $id, '_lpg_spec_valve', '"><img src=x onerror=alert(8)>' );

		// Admin: "LPG Specifications" panel.
		$GLOBALS['post'] = get_post( $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Panel reads global $post.
		ob_start();
		( new ProductSpecsPanel() )->render_panel();
		$admin = (string) ob_get_clean();

		$this->assertStringContainsString( 'lpg_spec[colour]', $admin, 'Panel rendered the field.' );
		$this->assert_no_raw_injection( $admin );
		$this->assertStringNotContainsString( '<script>alert(5)', $admin );
		$this->assertStringContainsString( 'value="Red&quot; onmouseover=&quot;alert(7)"', $admin );
		$this->assertStringContainsString( '&quot;&gt;&lt;img src=x onerror=alert(8)&gt;', $admin, 'Stale select value shown escaped.' );

		// Front end: spec table block.
		$front = SpecTable::render( array(), $id );
		$this->assertStringContainsString( '<table', $front );
		$this->assert_no_raw_injection( $front );
		$this->assertStringContainsString( 'Cap&quot;&gt;', $front );
		$this->assertStringContainsString( 'Red&quot; onmouseover=&quot;alert(7)', $front );
	}
}
