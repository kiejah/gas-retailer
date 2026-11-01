<?php
/**
 * "LPG Specifications" tab in the WooCommerce product data panel.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Specs;

use LPG\Commerce\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the resolved profile's fields and saves `_lpg_spec_{key}` values (§11.3).
 */
final class ProductSpecsPanel implements Service {

	private const NONCE       = 'lpg_save_product_specs';
	private const NONCE_FIELD = 'lpg_product_specs_nonce';

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Add the tab.
	 *
	 * @param array<string, array<string, mixed>> $tabs Product data tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_tab( $tabs ): array {
		$tabs = is_array( $tabs ) ? $tabs : array();

		$tabs['lpg_specs'] = array(
			'label'    => __( 'LPG Specifications', 'lpg-commerce-core' ),
			'target'   => 'lpg_specs_product_data',
			'class'    => array(),
			'priority' => 65,
		);

		return $tabs;
	}

	/**
	 * Render the panel.
	 */
	public function render_panel(): void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$product_id = (int) $post->ID;
		$override   = (int) get_post_meta( $product_id, ProfileResolver::OVERRIDE_META, true );
		$profile_id = ProfileResolver::for_product( $product_id );
		$fields     = $profile_id ? ProfileRepository::get_fields( $profile_id ) : array();
		$choices    = array( '0' => __( '— Use the category default —', 'lpg-commerce-core' ) );
		foreach ( ProfilePostType::profile_choices() as $id => $title ) {
			$choices[ (string) $id ] = $title;
		}
		?>
		<div id="lpg_specs_product_data" class="panel woocommerce_options_panel hidden">
			<?php wp_nonce_field( self::NONCE, self::NONCE_FIELD, false ); ?>
			<input type="hidden" name="lpg_spec_rendered_profile" value="<?php echo esc_attr( (string) (int) $profile_id ); ?>">
			<div class="options_group">
				<?php
				woocommerce_wp_select(
					array(
						'id'          => '_lpg_spec_profile_id',
						'label'       => __( 'Spec profile', 'lpg-commerce-core' ),
						'options'     => $choices,
						'value'       => ProfileRepository::is_profile( $override ) ? (string) $override : '0',
						'desc_tip'    => true,
						'description' => __( 'Normally the profile comes from the product category. Choose one here only to override it. Save the product to load the fields of a newly chosen profile.', 'lpg-commerce-core' ),
					)
				);
				?>
			</div>
			<?php if ( ! $profile_id ) : ?>
				<div class="inline notice notice-info lpg-specs-notice"><p>
					<?php esc_html_e( 'No spec profile applies to this product yet. Assign the product to a category that has a default spec profile, or choose a profile above. After changing the category or profile, save (or save as draft) to load its specification fields.', 'lpg-commerce-core' ); ?>
				</p></div>
			<?php elseif ( ! $fields ) : ?>
				<div class="inline notice notice-info lpg-specs-notice"><p>
					<?php esc_html_e( 'The spec profile for this product has no fields yet. Add fields under Products → Spec profiles.', 'lpg-commerce-core' ); ?>
				</p></div>
			<?php else : ?>
				<div class="options_group">
					<p class="form-field"><em>
						<?php
						printf(
							/* translators: %s: spec profile title. */
							esc_html__( 'Profile: %s. Leave a field empty if the supplier has not provided the value. After changing the category or profile, save to load its fields.', 'lpg-commerce-core' ),
							esc_html( get_the_title( $profile_id ) )
						);
						?>
					</em></p>
					<?php
					foreach ( $fields as $field ) {
						$this->render_field( $product_id, $field );
					}
					?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render one spec field input.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $field      Field definition.
	 */
	private function render_field( int $product_id, array $field ): void {
		$value = (string) get_post_meta( $product_id, ProfileRepository::value_meta_key( $field['key'] ), true );
		$label = '' !== $field['unit']
			/* translators: 1: field label, 2: unit. */
			? sprintf( __( '%1$s (%2$s)', 'lpg-commerce-core' ), $field['label'], $field['unit'] )
			: $field['label'];
		$args = array(
			'id'          => 'lpg_spec_' . $field['key'],
			'name'        => 'lpg_spec[' . $field['key'] . ']',
			'label'       => $label,
			'value'       => $value,
			'desc_tip'    => '' !== $field['help'],
			'description' => $field['help'],
		);

		if ( 'select' === $field['type'] || 'boolean' === $field['type'] ) {
			$options = array( '' => __( '—', 'lpg-commerce-core' ) );
			if ( 'boolean' === $field['type'] ) {
				$options['yes'] = __( 'Yes', 'lpg-commerce-core' );
				$options['no']  = __( 'No', 'lpg-commerce-core' );
			} else {
				foreach ( $field['options'] as $option ) {
					$options[ $option ] = $option;
				}
				if ( '' !== $value && ! isset( $options[ $value ] ) ) {
					/* translators: %s: stored value that is no longer an option. */
					$options[ $value ] = sprintf( __( '%s (no longer an option)', 'lpg-commerce-core' ), $value );
				}
			}
			$args['options'] = $options;
			woocommerce_wp_select( $args );
			return;
		}

		if ( 'number' === $field['type'] ) {
			$args['type']              = 'text';
			$args['custom_attributes'] = array( 'inputmode' => 'decimal' );
		}
		woocommerce_wp_text_input( $args );
	}

	/**
	 * Save handler (WooCommerce product object, before it is written).
	 *
	 * @param \WC_Product $product Product.
	 */
	public function handle_save( $product ): void {
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}

		$override = isset( $_POST['_lpg_spec_profile_id'] ) ? absint( wp_unslash( $_POST['_lpg_spec_profile_id'] ) ) : 0;
		$rendered = isset( $_POST['lpg_spec_rendered_profile'] ) ? absint( wp_unslash( $_POST['lpg_spec_rendered_profile'] ) ) : 0;
		// Values are validated per field type in apply().
		$values = isset( $_POST['lpg_spec'] ) && is_array( $_POST['lpg_spec'] ) ? wp_unslash( $_POST['lpg_spec'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$errors = self::apply( $product, $override, $rendered, $values );
		if ( $errors && class_exists( \WC_Admin_Meta_Boxes::class ) ) {
			foreach ( $errors as $error ) {
				\WC_Admin_Meta_Boxes::add_error( $error );
			}
		}
	}

	/**
	 * Apply the override and spec values to a product object (not saved here).
	 *
	 * Only fields of `$rendered_profile` (the profile whose fields were shown)
	 * are written; keys missing from `$values` are left as they are. Empty values
	 * delete the meta. Rejected values are not saved and keep the previous value.
	 *
	 * @param \WC_Product              $product          Product.
	 * @param int                      $override         Override profile ID (0 = inherit).
	 * @param int                      $rendered_profile Profile whose fields were submitted.
	 * @param array<string|int, mixed> $values           Raw (unslashed) values keyed by field key.
	 * @return string[] Messages for rejected values.
	 */
	public static function apply( \WC_Product $product, int $override, int $rendered_profile, array $values ): array {
		if ( ProfileRepository::is_profile( $override ) ) {
			$product->update_meta_data( ProfileResolver::OVERRIDE_META, $override );
		} else {
			$product->delete_meta_data( ProfileResolver::OVERRIDE_META );
		}

		$errors = array();
		foreach ( ProfileRepository::get_fields( $rendered_profile ) as $field ) {
			if ( ! array_key_exists( $field['key'], $values ) ) {
				continue;
			}
			$result   = ProfileRepository::sanitize_value( $field, $values[ $field['key'] ] );
			$meta_key = ProfileRepository::value_meta_key( $field['key'] );
			if ( $result['error'] ) {
				$errors[] = sprintf(
					/* translators: %s: field label. */
					__( 'LPG Specifications: the value for "%s" was not saved because it is not valid for this field.', 'lpg-commerce-core' ),
					$field['label']
				);
				continue;
			}
			if ( '' === $result['value'] ) {
				$product->delete_meta_data( $meta_key );
			} else {
				$product->update_meta_data( $meta_key, $result['value'] );
			}
		}

		return $errors;
	}

	/**
	 * Small admin stylesheet on the product edit screen.
	 *
	 * @param string $hook_suffix Admin page hook.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && 'product' === $screen->post_type ) {
			wp_enqueue_style( 'lpg-admin', LPG_COMMERCE_URL . 'assets/admin/admin.css', array(), LPG_COMMERCE_VERSION );
		}
	}
}
