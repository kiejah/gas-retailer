<?php
/**
 * CAT-076: two-way compatibility mirroring must not loop. Saving A writes B's
 * list once and does not re-enter any save path.
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgFixtures.php';

use LPG\Commerce\Relations\Compatibility;

class CompatibilityRecursionTest extends WP_UnitTestCase {

	use LpgFixtures;

	/**
	 * Writes of _lpg_compatible_ids, keyed by product ID.
	 *
	 * @var array<int, int>
	 */
	private array $writes = array();

	/**
	 * Calls of save_post, keyed by post ID.
	 *
	 * @var array<int, int>
	 */
	private array $saves = array();

	/**
	 * Calls of woocommerce_process_product_meta, keyed by product ID.
	 *
	 * @var array<int, int>
	 */
	private array $processed = array();

	private int $depth = 0;

	private int $max_depth = 0;

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
	}

	public function tear_down(): void {
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Count meta writes, saves and save-handler nesting from here on.
	 */
	private function start_counting(): void {
		$this->writes    = array();
		$this->saves     = array();
		$this->processed = array();
		$count_write     = function ( $meta_id, $object_id, $meta_key ): void {
			if ( Compatibility::META === $meta_key ) {
				$this->writes[ (int) $object_id ] = ( $this->writes[ (int) $object_id ] ?? 0 ) + 1;
			}
		};
		add_action( 'added_post_meta', $count_write, 10, 3 );
		add_action( 'updated_post_meta', $count_write, 10, 3 );
		add_action( 'deleted_post_meta', $count_write, 10, 3 );
		add_action(
			'save_post',
			function ( $post_id ): void {
				$this->saves[ (int) $post_id ] = ( $this->saves[ (int) $post_id ] ?? 0 ) + 1;
			}
		);
		// Wrap the plugin's handler (priority 20) to measure re-entrancy.
		add_action(
			'woocommerce_process_product_meta',
			function ( $post_id ): void {
				$this->processed[ (int) $post_id ] = ( $this->processed[ (int) $post_id ] ?? 0 ) + 1;
				++$this->depth;
				$this->max_depth = max( $this->max_depth, $this->depth );
			},
			19
		);
		add_action(
			'woocommerce_process_product_meta',
			function (): void {
				--$this->depth;
			},
			21
		);
	}

	/**
	 * Save product A the way the product edit screen does: product object save
	 * (save_post) followed by woocommerce_process_product_meta with the nonce.
	 *
	 * @param int   $product_id Product ID.
	 * @param int[] $ids        Compatible IDs submitted.
	 */
	private function save_from_edit_screen( int $product_id, array $ids ): void {
		$_POST = array(
			'lpg_compatibility_nonce' => wp_create_nonce( 'lpg_save_compatibility' ),
			'lpg_compatible_ids'      => array_map( 'strval', $ids ),
		);
		wc_get_product( $product_id )->save();
		do_action( 'woocommerce_process_product_meta', $product_id, get_post( $product_id ) );
		$_POST = array();
	}

	public function test_linking_writes_each_side_once_without_reentering_saves(): void {
		$a = $this->create_product()->get_id();
		$b = $this->create_product()->get_id();
		$this->start_counting();

		$this->save_from_edit_screen( $a, array( $b ) );

		$this->assertSame( array( $b ), Compatibility::stored_ids( $a ) );
		$this->assertSame( array( $a ), Compatibility::stored_ids( $b ) );
		$this->assertSame( 1, $this->writes[ $a ] ?? 0, 'A written once.' );
		$this->assertSame( 1, $this->writes[ $b ] ?? 0, 'B (mirror) written once.' );
		$this->assertArrayNotHasKey( $b, $this->saves, 'Mirror does not save B as a post.' );
		$this->assertSame( array( $a => 1 ), $this->processed, 'Save handler ran once, for A only.' );
		$this->assertSame( 1, $this->max_depth, 'No nested save handler call.' );
	}

	public function test_resaving_linked_products_does_not_rewrite_the_mirror(): void {
		$a = $this->create_product()->get_id();
		$b = $this->create_product()->get_id();
		$c = $this->create_product()->get_id();
		Compatibility::save( $a, array( $b ) );
		$this->start_counting();

		// A↔B already linked: save A unchanged, then B unchanged, then add C to A.
		$this->save_from_edit_screen( $a, array( $b ) );
		$this->save_from_edit_screen( $b, array( $a ) );
		$this->assertSame( array(), $this->writes, 'Unchanged links: no meta writes on either side.' );
		$this->assertSame(
			array(
				$a => 1,
				$b => 1,
			),
			$this->processed
		);

		$before_b = $this->writes[ $b ] ?? 0;
		$this->save_from_edit_screen( $a, array( $b, $c ) );

		$this->assertSame( array( $b, $c ), Compatibility::stored_ids( $a ) );
		$this->assertSame( array( $a ), Compatibility::stored_ids( $b ) );
		$this->assertSame( array( $a ), Compatibility::stored_ids( $c ) );
		$this->assertSame( $before_b, $this->writes[ $b ] ?? 0, 'Existing link B untouched when C is added.' );
		$this->assertSame( 1, $this->writes[ $c ] ?? 0, 'C (mirror) written once.' );
		$this->assertArrayNotHasKey( $c, $this->saves );
		$this->assertSame( 1, $this->max_depth );
	}

	public function test_unlinking_removes_mirror_once(): void {
		$a = $this->create_product()->get_id();
		$b = $this->create_product()->get_id();
		Compatibility::save( $a, array( $b ) );
		$this->start_counting();

		$this->save_from_edit_screen( $a, array() );

		$this->assertSame( array(), Compatibility::stored_ids( $a ) );
		$this->assertSame( array(), Compatibility::stored_ids( $b ) );
		$this->assertSame( 1, $this->writes[ $b ] ?? 0, 'B updated once (meta deleted).' );
		$this->assertArrayNotHasKey( $b, $this->saves );
		$this->assertSame( 1, $this->max_depth );
	}

	public function test_mirror_does_not_loop_when_a_third_party_saves_the_linked_product(): void {
		// Worst case: another plugin re-saves any product whose compatibility meta
		// changes, running the full edit-screen save again. Linking must still settle.
		$a       = $this->create_product()->get_id();
		$b       = $this->create_product()->get_id();
		$resaved = array();
		$resave  = function ( $meta_id, $object_id, $meta_key ) use ( &$resaved ): void {
			if ( Compatibility::META !== $meta_key || isset( $resaved[ $object_id ] ) || $this->depth > 5 ) {
				return;
			}
			$resaved[ $object_id ] = true;
			$ids                   = Compatibility::stored_ids( (int) $object_id );
			$this->save_from_edit_screen( (int) $object_id, $ids );
		};
		$this->start_counting();
		add_action( 'added_post_meta', $resave, 20, 3 );
		add_action( 'updated_post_meta', $resave, 20, 3 );

		$this->save_from_edit_screen( $a, array( $b ) );

		$this->assertSame( array( $b ), Compatibility::stored_ids( $a ) );
		$this->assertSame( array( $a ), Compatibility::stored_ids( $b ) );
		$this->assertLessThanOrEqual( 2, $this->max_depth, 'Re-saving B does not mirror back onto A again.' );
		$this->assertSame( 1, $this->writes[ $b ] ?? 0, 'B written once even though it was re-saved.' );
		$this->assertSame( 1, $this->writes[ $a ] ?? 0, 'A written once: B\'s re-save sees the link and writes nothing.' );
	}
}
