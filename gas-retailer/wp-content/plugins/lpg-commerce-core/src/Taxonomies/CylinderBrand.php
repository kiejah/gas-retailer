<?php
/**
 * Cylinder brand taxonomy for the refill / exchange flow.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Taxonomies;

use LPG\Commerce\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Registers `lpg_cylinder_brand` (§13.2): brands the shop refills or
 * exchanges, each with the sizes it offers in term meta `lpg_sizes`.
 *
 * Not attached to any post type. Managed under Products with the native
 * add / edit / delete term screens, so the client changes brands and sizes
 * without code. Read with CylinderBrands::all().
 */
final class CylinderBrand implements Service {

	public const TAXONOMY = 'lpg_cylinder_brand';

	public const META_SIZES = 'lpg_sizes';

	public const MAX_SIZES = 20;

	public const MAX_SIZE_LENGTH = 20;

	/**
	 * Brands seeded by `wp lpg seed`, slug => [ name, sizes ]. Owner-provided
	 * starting data (owner-decisions, 2026-11-01). Stored as content.
	 */
	public const SEED = array(
		'k-gas'   => array(
			'name'  => 'K-Gas',
			'sizes' => array( '6kg', '13kg' ),
		),
		'total'   => array(
			'name'  => 'Total',
			'sizes' => array( '6kg', '13kg' ),
		),
		'pro-gas' => array(
			'name'  => 'Pro Gas',
			'sizes' => array( '6kg', '13kg' ),
		),
	);

	private const NONCE       = 'lpg_save_cylinder_sizes';
	private const NONCE_FIELD = 'lpg_cylinder_sizes_nonce';
	private const FIELD       = 'lpg_sizes';

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'init', array( self::class, 'register_taxonomy' ) );
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );

		add_action( self::TAXONOMY . '_add_form_fields', array( $this, 'render_add_field' ) );
		add_action( self::TAXONOMY . '_edit_form_fields', array( $this, 'render_edit_field' ) );
		add_action( 'created_' . self::TAXONOMY, array( $this, 'handle_save' ) );
		add_action( 'edited_' . self::TAXONOMY, array( $this, 'handle_save' ) );

		add_filter( 'manage_edit-' . self::TAXONOMY . '_columns', array( $this, 'columns' ) );
		add_filter( 'manage_' . self::TAXONOMY . '_custom_column', array( $this, 'column_content' ), 10, 3 );
	}

	/**
	 * Register the taxonomy and its term meta. Runs on `init`.
	 */
	public static function register_taxonomy(): void {
		register_taxonomy(
			self::TAXONOMY,
			array(),
			array_merge(
				self::args(),
				array(
					'labels' => array(
						'name'          => __( 'Cylinder brands', 'lpg-commerce-core' ),
						'singular_name' => __( 'Cylinder brand', 'lpg-commerce-core' ),
						'menu_name'     => __( 'Cylinder brands', 'lpg-commerce-core' ),
						'all_items'     => __( 'All cylinder brands', 'lpg-commerce-core' ),
						'edit_item'     => __( 'Edit cylinder brand', 'lpg-commerce-core' ),
						'view_item'     => __( 'View cylinder brand', 'lpg-commerce-core' ),
						'update_item'   => __( 'Update cylinder brand', 'lpg-commerce-core' ),
						'add_new_item'  => __( 'Add new cylinder brand', 'lpg-commerce-core' ),
						'new_item_name' => __( 'New cylinder brand name', 'lpg-commerce-core' ),
						'search_items'  => __( 'Search cylinder brands', 'lpg-commerce-core' ),
						'not_found'     => __( 'No cylinder brands found.', 'lpg-commerce-core' ),
						'back_to_items' => __( '&larr; Back to cylinder brands', 'lpg-commerce-core' ),
					),
				)
			)
		);
		self::register_meta();
	}

	/**
	 * Register without translated labels, for use before `init` (seed/installer).
	 */
	public static function register_minimal(): void {
		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			register_taxonomy( self::TAXONOMY, array(), self::args() );
			self::register_meta();
		}
	}

	/**
	 * Register the `lpg_sizes` term meta.
	 */
	private static function register_meta(): void {
		register_term_meta(
			self::TAXONOMY,
			self::META_SIZES,
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'show_in_rest'      => false,
				'sanitize_callback' => array( self::class, 'sanitize_sizes' ),
				'auth_callback'     => static fn( $allowed, $meta_key, $term_id ) => current_user_can( 'edit_term', (int) $term_id ),
			)
		);
	}

	/**
	 * Add "Cylinder brands" under Products.
	 */
	public function add_menu(): void {
		$tax = get_taxonomy( self::TAXONOMY );
		if ( ! $tax ) {
			return;
		}
		add_submenu_page(
			'edit.php?post_type=product',
			$tax->labels->name,
			$tax->labels->menu_name,
			$tax->cap->manage_terms,
			'edit-tags.php?taxonomy=' . self::TAXONOMY . '&post_type=product'
		);
	}

	/**
	 * Sanitise sizes: one per line (string) or a list. Trimmed, plain text,
	 * de-duplicated case-insensitively (first spelling kept), each cut to
	 * MAX_SIZE_LENGTH characters, at most MAX_SIZES entries.
	 *
	 * @param mixed $raw Raw value.
	 * @return string[]
	 */
	public static function sanitize_sizes( $raw ): array {
		if ( is_string( $raw ) ) {
			$raw = preg_split( '/\r\n|\r|\n/', $raw );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$sizes = array();
		$seen  = array();
		foreach ( $raw as $size ) {
			if ( ! is_scalar( $size ) ) {
				continue;
			}
			$size = trim( mb_substr( sanitize_text_field( (string) $size ), 0, self::MAX_SIZE_LENGTH ) );
			$key  = mb_strtolower( $size );
			if ( '' === $size || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$sizes[]      = $size;
			if ( count( $sizes ) >= self::MAX_SIZES ) {
				break;
			}
		}
		return $sizes;
	}

	/**
	 * Sizes for a brand.
	 *
	 * @param int $term_id Term ID.
	 * @return string[]
	 */
	public static function get_sizes( int $term_id ): array {
		$sizes = get_term_meta( $term_id, self::META_SIZES, true );
		return is_array( $sizes ) ? array_values( array_filter( $sizes, 'is_string' ) ) : array();
	}

	/**
	 * Store sizes for a brand (sanitised).
	 *
	 * @param int   $term_id Term ID.
	 * @param mixed $sizes   Sizes, one per line or a list.
	 */
	public static function save_sizes( int $term_id, $sizes ): void {
		update_term_meta( $term_id, self::META_SIZES, self::sanitize_sizes( $sizes ) );
	}

	/**
	 * Sizes textarea on the "Add brand" form.
	 */
	public function render_add_field(): void {
		?>
		<div class="form-field term-lpg-sizes-wrap">
			<?php wp_nonce_field( self::NONCE, self::NONCE_FIELD, false ); ?>
			<label for="lpg-sizes"><?php esc_html_e( 'Sizes', 'lpg-commerce-core' ); ?></label>
			<textarea name="<?php echo esc_attr( self::FIELD ); ?>" id="lpg-sizes" rows="4"></textarea>
			<p><?php echo esc_html( self::help_text() ); ?></p>
		</div>
		<?php
	}

	/**
	 * Sizes textarea on the "Edit brand" form.
	 *
	 * @param \WP_Term $term Term being edited.
	 */
	public function render_edit_field( \WP_Term $term ): void {
		?>
		<tr class="form-field term-lpg-sizes-wrap">
			<th scope="row"><label for="lpg-sizes"><?php esc_html_e( 'Sizes', 'lpg-commerce-core' ); ?></label></th>
			<td>
				<?php wp_nonce_field( self::NONCE, self::NONCE_FIELD, false ); ?>
				<textarea name="<?php echo esc_attr( self::FIELD ); ?>" id="lpg-sizes" rows="4"><?php echo esc_textarea( implode( "\n", self::get_sizes( $term->term_id ) ) ); ?></textarea>
				<p class="description"><?php echo esc_html( self::help_text() ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save sizes from the term forms.
	 *
	 * @param int $term_id Term ID.
	 */
	public function handle_save( int $term_id ): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_term', $term_id ) || ! isset( $_POST[ self::FIELD ] ) || ! is_string( $_POST[ self::FIELD ] ) ) {
			return;
		}
		// Sanitised line by line in sanitize_sizes().
		self::save_sizes( $term_id, sanitize_textarea_field( wp_unslash( $_POST[ self::FIELD ] ) ) );
	}

	/**
	 * Replace the (meaningless) post count column with a sizes column.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( $columns ): array {
		$columns = is_array( $columns ) ? $columns : array();
		unset( $columns['posts'] );
		$columns['lpg_sizes'] = __( 'Sizes', 'lpg-commerce-core' );
		return $columns;
	}

	/**
	 * Sizes column content.
	 *
	 * @param string $content Existing content.
	 * @param string $column  Column name.
	 * @param int    $term_id Term ID.
	 * @return string
	 */
	public function column_content( $content, $column, $term_id ): string {
		if ( 'lpg_sizes' !== $column ) {
			return (string) $content;
		}
		$sizes = self::get_sizes( (int) $term_id );
		return $sizes ? esc_html( implode( ', ', $sizes ) ) : '&mdash;';
	}

	/**
	 * Create the seed brands that are missing. Existing brands (and their
	 * sizes) are never changed (C8).
	 *
	 * @return array{created: int, existing: int, log: string[], errors: string[]}
	 */
	public static function seed(): array {
		self::register_minimal();
		$result = array(
			'created'  => 0,
			'existing' => 0,
			'log'      => array(),
			'errors'   => array(),
		);
		foreach ( self::SEED as $slug => $brand ) {
			if ( get_term_by( 'slug', $slug, self::TAXONOMY ) ) {
				++$result['existing'];
				continue;
			}
			$inserted = wp_insert_term( $brand['name'], self::TAXONOMY, array( 'slug' => $slug ) );
			if ( is_wp_error( $inserted ) ) {
				$result['errors'][] = sprintf( 'Could not create cylinder brand "%s": %s', $brand['name'], $inserted->get_error_message() );
				continue;
			}
			self::save_sizes( (int) $inserted['term_id'], $brand['sizes'] );
			++$result['created'];
			$result['log'][] = sprintf( 'Created cylinder brand "%s" (#%d) with sizes %s.', $brand['name'], $inserted['term_id'], implode( ', ', $brand['sizes'] ) );
		}
		return $result;
	}

	/**
	 * Help text under the sizes field.
	 */
	private static function help_text(): string {
		return sprintf(
			/* translators: 1: maximum number of sizes, 2: maximum characters per size. */
			__( 'One size per line, for example 6kg. Up to %1$d sizes, %2$d characters each. These appear in the refill and exchange form.', 'lpg-commerce-core' ),
			self::MAX_SIZES,
			self::MAX_SIZE_LENGTH
		);
	}

	/**
	 * Registration arguments shared by both registration paths.
	 *
	 * @return array<string, mixed>
	 */
	private static function args(): array {
		return array(
			'hierarchical'       => false,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'show_in_nav_menus'  => false,
			'show_tagcloud'      => false,
			'show_in_quick_edit' => false,
			'show_admin_column'  => false,
			'show_in_rest'       => false,
			'meta_box_cb'        => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capabilities'       => array(
				'manage_terms' => 'manage_product_terms',
				'edit_terms'   => 'edit_product_terms',
				'delete_terms' => 'delete_product_terms',
				'assign_terms' => 'assign_product_terms',
			),
		);
	}
}
