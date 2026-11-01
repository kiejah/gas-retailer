<?php
/**
 * Compatible products (`_lpg_compatible_ids`), optionally two-way.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Relations;

use LPG\Commerce\Service;

defined( 'ABSPATH' ) || exit;

/**
 * §11.4: Linked Products field, two-way mirroring, get_ids() for display.
 */
final class Compatibility implements Service {

	public const META = '_lpg_compatible_ids';

	private const NONCE       = 'lpg_save_compatibility';
	private const NONCE_FIELD = 'lpg_compatibility_nonce';

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_product_options_related', array( $this, 'render_field' ) );
		// After WC_Meta_Box_Product_Data::save (priority 10) has written the product.
		add_action( 'woocommerce_process_product_meta', array( $this, 'handle_save' ), 20 );
		add_action( 'before_delete_post', array( self::class, 'handle_delete' ), 10, 2 );
	}

	/**
	 * Compatible products for display: published and visible only (§11.6).
	 *
	 * @param int $product_id Product ID.
	 * @return int[]
	 */
	public static function get_ids( int $product_id ): array {
		$ids    = array();
		$stored = self::stored_ids( $product_id );
		if ( $stored ) {
			// Load posts, meta and terms for all linked products in a few queries instead of per product (DEF-007).
			_prime_post_caches( $stored, true, true );
		}
		foreach ( $stored as $id ) {
			$product = wc_get_product( $id );
			if ( $product instanceof \WC_Product && 'publish' === $product->get_status() && $product->is_visible() ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Raw stored IDs (any status).
	 *
	 * @param int $product_id Product ID.
	 * @return int[]
	 */
	public static function stored_ids( int $product_id ): array {
		$ids = get_post_meta( $product_id, self::META, true );

		return is_array( $ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) : array();
	}

	/**
	 * Save a product's compatible IDs and mirror additions/removals onto the
	 * other products when two-way sync is on. Mirrors are written straight to
	 * meta (no product save, no hooks), so there is no recursion.
	 *
	 * @param int        $product_id Product ID.
	 * @param array<int> $ids        Compatible product IDs.
	 * @return int[] Stored IDs.
	 */
	public static function save( int $product_id, array $ids ): array {
		$old = self::stored_ids( $product_id );
		$new = self::sanitize_ids( $product_id, $ids, $old );

		// Keep stored links to products the current user cannot edit (L1).
		foreach ( $old as $id ) {
			if ( ! in_array( $id, $new, true ) && ! current_user_can( 'edit_post', $id ) ) {
				$new[] = $id;
			}
		}
		self::write( $product_id, $new );

		/**
		 * Whether compatibility links are mirrored onto the linked products.
		 *
		 * @param bool $bidirectional Default true.
		 * @param int  $product_id    Product being saved.
		 */
		if ( apply_filters( 'lpg_compatibility_bidirectional', true, $product_id ) ) {
			self::mirror( $product_id, array_diff( $new, $old ), array_diff( $old, $new ) );
		}

		return $new;
	}

	/**
	 * Mirror added/removed links onto the other products the user can edit (L1).
	 *
	 * @param int   $product_id Product being saved.
	 * @param int[] $added      Newly linked product IDs.
	 * @param int[] $removed    Unlinked product IDs.
	 */
	private static function mirror( int $product_id, array $added, array $removed ): void {
		foreach ( $added as $other ) {
			$list = self::stored_ids( $other );
			if ( current_user_can( 'edit_post', $other ) && ! in_array( $product_id, $list, true ) ) {
				$list[] = $product_id;
				self::write( $other, $list );
			}
		}
		foreach ( $removed as $other ) {
			$list = self::stored_ids( $other );
			if ( current_user_can( 'edit_post', $other ) && in_array( $product_id, $list, true ) ) {
				self::write( $other, array_values( array_diff( $list, array( $product_id ) ) ) );
			}
		}
	}

	/**
	 * Render the product search select in Linked Products.
	 */
	public function render_field(): void {
		global $post;
		$product_id = $post instanceof \WP_Post ? (int) $post->ID : 0;
		wp_nonce_field( self::NONCE, self::NONCE_FIELD, false );
		?>
		<div class="options_group">
			<p class="form-field">
				<label for="lpg_compatible_ids"><?php esc_html_e( 'Compatible with', 'lpg-commerce-core' ); ?></label>
				<select class="wc-product-search" multiple="multiple" style="width: 50%;" id="lpg_compatible_ids" name="lpg_compatible_ids[]" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'lpg-commerce-core' ); ?>" data-action="woocommerce_json_search_products" data-exclude="<?php echo esc_attr( (string) $product_id ); ?>">
					<?php
					foreach ( self::stored_ids( $product_id ) as $id ) {
						$product = wc_get_product( $id );
						if ( $product instanceof \WC_Product ) {
							echo '<option value="' . esc_attr( (string) $id ) . '" selected="selected">' . esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) . '</option>';
						}
					}
					?>
				</select>
				<?php echo wc_help_tip( __( 'Products this item works with, e.g. a regulator and the cylinders or appliances it fits. Each link is also added to the other product.', 'lpg-commerce-core' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip escapes. ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Save handler.
	 *
	 * @param int $post_id Product ID.
	 */
	public function handle_save( $post_id ): void {
		$post_id = (int) $post_id;
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$ids = isset( $_POST['lpg_compatible_ids'] ) && is_array( $_POST['lpg_compatible_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['lpg_compatible_ids'] ) ) : array();
		self::save( $post_id, $ids );
	}

	/**
	 * Remove a permanently deleted product from every other product's list (F3).
	 *
	 * @param int           $post_id Post being deleted.
	 * @param \WP_Post|null $post    Post object.
	 */
	public static function handle_delete( $post_id, $post = null ): void {
		$post_id = (int) $post_id;
		$post    = $post instanceof \WP_Post ? $post : get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'product' !== $post->post_type ) {
			return;
		}
		// Serialised int arrays contain "i:{id};" for each value; re-checked below.
		$referencing = get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Runs only on permanent product deletion.
					array(
						'key'     => self::META,
						'value'   => 'i:' . $post_id . ';',
						'compare' => 'LIKE',
					),
				),
			)
		);
		foreach ( $referencing as $other ) {
			$list = self::stored_ids( (int) $other );
			if ( in_array( $post_id, $list, true ) ) {
				self::write( (int) $other, array_values( array_diff( $list, array( $post_id ) ) ) );
			}
		}
	}

	/**
	 * Keep existing, non-trashed products only; drop self and duplicates.
	 * IDs not already stored are accepted only if the user can edit them (L1).
	 *
	 * @param int        $product_id Product ID.
	 * @param array<int> $ids        Raw IDs.
	 * @param int[]      $old        Currently stored IDs.
	 * @return int[]
	 */
	private static function sanitize_ids( int $product_id, array $ids, array $old ): array {
		$clean = array();
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( ! $id || $id === $product_id || in_array( $id, $clean, true ) ) {
				continue;
			}
			$post = get_post( $id );
			if ( ! $post instanceof \WP_Post || 'product' !== $post->post_type || 'trash' === $post->post_status ) {
				continue;
			}
			if ( in_array( $id, $old, true ) || current_user_can( 'edit_post', $id ) ) {
				$clean[] = $id;
			}
		}

		return $clean;
	}

	/**
	 * Write or delete the meta.
	 *
	 * @param int   $product_id Product ID.
	 * @param int[] $ids        IDs.
	 */
	private static function write( int $product_id, array $ids ): void {
		if ( $ids ) {
			update_post_meta( $product_id, self::META, array_values( $ids ) );
		} else {
			delete_post_meta( $product_id, self::META );
		}
	}
}
