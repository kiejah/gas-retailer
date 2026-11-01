<?php
/**
 * Spec profile CPT, field-definitions meta box and category default profile.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Specs;

use LPG\Commerce\Service;

defined( 'ABSPATH' ) || exit;

/**
 * `lpg_spec_profile` (§11.3).
 */
final class ProfilePostType implements Service {

	public const POST_TYPE = 'lpg_spec_profile';

	/**
	 * Primitive capabilities granted to administrator and shop_manager.
	 */
	public const CAPS = array(
		'edit_lpg_spec_profiles',
		'edit_others_lpg_spec_profiles',
		'edit_private_lpg_spec_profiles',
		'edit_published_lpg_spec_profiles',
		'publish_lpg_spec_profiles',
		'read_private_lpg_spec_profiles',
		'delete_lpg_spec_profiles',
		'delete_others_lpg_spec_profiles',
		'delete_private_lpg_spec_profiles',
		'delete_published_lpg_spec_profiles',
	);

	/**
	 * Roles that receive CAPS.
	 */
	public const ROLES = array( 'administrator', 'shop_manager' );

	private const NONCE         = 'lpg_save_spec_profile';
	private const NONCE_FIELD   = 'lpg_spec_profile_nonce';
	private const TERM_NONCE    = 'lpg_save_category_profile';
	private const TERM_FIELD    = 'lpg_category_profile_nonce';
	private const NOTICE_PREFIX = 'lpg_profile_notice_';

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'init', array( self::class, 'register_post_type' ) );
		add_action( 'init', array( self::class, 'register_term_meta' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'handle_save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'render_notices' ) );

		add_action( 'product_cat_add_form_fields', array( $this, 'render_add_term_field' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'render_edit_term_field' ) );
		add_action( 'created_product_cat', array( $this, 'handle_term_save' ) );
		add_action( 'edited_product_cat', array( $this, 'handle_term_save' ) );
	}

	/**
	 * Register the CPT.
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Spec profiles', 'lpg-commerce-core' ),
					'singular_name'      => __( 'Spec profile', 'lpg-commerce-core' ),
					'menu_name'          => __( 'Spec profiles', 'lpg-commerce-core' ),
					'all_items'          => __( 'Spec profiles', 'lpg-commerce-core' ),
					'add_new_item'       => __( 'Add new spec profile', 'lpg-commerce-core' ),
					'edit_item'          => __( 'Edit spec profile', 'lpg-commerce-core' ),
					'new_item'           => __( 'New spec profile', 'lpg-commerce-core' ),
					'search_items'       => __( 'Search spec profiles', 'lpg-commerce-core' ),
					'not_found'          => __( 'No spec profiles found.', 'lpg-commerce-core' ),
					'not_found_in_trash' => __( 'No spec profiles found in Trash.', 'lpg-commerce-core' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=product',
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => array( 'lpg_spec_profile', 'lpg_spec_profiles' ),
				'map_meta_cap'    => true,
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}

	/**
	 * Register the product_cat default-profile term meta.
	 */
	public static function register_term_meta(): void {
		register_term_meta(
			'product_cat',
			ProfileResolver::TERM_META,
			array(
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => 'absint',
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Grant CAPS to ROLES. Called by the installer.
	 */
	public static function add_caps(): void {
		foreach ( self::ROLES as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( self::CAPS as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Save validated field definitions and create missing `pa_` attributes.
	 *
	 * @param int                      $profile_id Profile post ID.
	 * @param array<int|string, mixed> $rows       Raw (unslashed) rows.
	 * @return string[] Errors for rejected rows (valid rows are still saved, C1).
	 */
	public static function save_fields( int $profile_id, array $rows ): array {
		$result = ProfileRepository::validate_definitions( $rows );
		update_post_meta( $profile_id, ProfileRepository::FIELDS_META, $result['fields'] );

		$errors = $result['errors'];
		foreach ( $result['fields'] as $field ) {
			if ( $field['filterable'] && ! AttributeSync::ensure_attribute( $field['key'], $field['label'] ) ) {
				/* translators: %s: field key. */
				$errors[] = sprintf( __( 'Could not create the product attribute for "%s".', 'lpg-commerce-core' ), $field['key'] );
			}
		}

		return $errors;
	}

	/**
	 * Published profiles for select lists, ID => title.
	 *
	 * @return array<int, string>
	 */
	public static function profile_choices(): array {
		$posts   = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'numberposts'      => 100,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);
		$choices = array();
		foreach ( $posts as $post ) {
			$choices[ $post->ID ] = '' !== $post->post_title ? $post->post_title : sprintf( '#%d', $post->ID );
		}

		return $choices;
	}

	/**
	 * Store or clear a category's default profile.
	 *
	 * @param int $term_id    product_cat term ID.
	 * @param int $profile_id Profile ID; 0 clears it. Invalid IDs clear it.
	 */
	public static function save_category_profile( int $term_id, int $profile_id ): void {
		if ( ProfileRepository::is_profile( $profile_id ) ) {
			update_term_meta( $term_id, ProfileResolver::TERM_META, $profile_id );
		} else {
			delete_term_meta( $term_id, ProfileResolver::TERM_META );
		}
	}

	/**
	 * Add the field-definitions meta box.
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'lpg-spec-fields',
			__( 'Field definitions', 'lpg-commerce-core' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the repeatable field rows.
	 *
	 * @param \WP_Post $post Profile post.
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE_FIELD, false );
		$stored = get_post_meta( $post->ID, ProfileRepository::FIELDS_META, true );
		$fields = is_array( $stored ) ? ProfileRepository::validate_definitions( $stored )['fields'] : array();
		?>
		<p class="description">
			<?php esc_html_e( 'Keys start with a lowercase letter and use lowercase letters, digits and underscores (28 characters at most). A key is stored with each product, so avoid changing it once products use it. Filterable fields are also written to a global product attribute (pa_key) for storefront filtering. Changing a label does not rename an existing attribute: rename it under Products → Attributes.', 'lpg-commerce-core' ); ?>
		</p>
		<table class="widefat lpg-fields-table" data-lpg-fields>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Key', 'lpg-commerce-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Label', 'lpg-commerce-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Type', 'lpg-commerce-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Unit', 'lpg-commerce-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Options (select only, one per line)', 'lpg-commerce-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Filterable', 'lpg-commerce-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Help text', 'lpg-commerce-core' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'lpg-commerce-core' ); ?></span></th>
				</tr>
			</thead>
			<tbody data-lpg-rows>
				<?php
				foreach ( array_values( $fields ) as $index => $field ) {
					$this->render_row( (string) $index, $field );
				}
				?>
			</tbody>
		</table>
		<template data-lpg-row-template>
			<?php
			$this->render_row(
				'__INDEX__',
				array(
					'key'        => '',
					'label'      => '',
					'type'       => 'text',
					'unit'       => '',
					'options'    => array(),
					'filterable' => false,
					'help'       => '',
				)
			);
			?>
		</template>
		<p><button type="button" class="button" data-lpg-add-row><?php esc_html_e( 'Add field', 'lpg-commerce-core' ); ?></button></p>
		<?php
	}

	/**
	 * One field row.
	 *
	 * @param string               $index Row index or placeholder.
	 * @param array<string, mixed> $field Field definition.
	 */
	private function render_row( string $index, array $field ): void {
		$name  = 'lpg_fields[' . $index . ']';
		$types = array(
			'text'    => __( 'Text', 'lpg-commerce-core' ),
			'number'  => __( 'Number', 'lpg-commerce-core' ),
			'select'  => __( 'Select', 'lpg-commerce-core' ),
			'boolean' => __( 'Yes/No', 'lpg-commerce-core' ),
		);
		?>
		<tr data-lpg-row>
			<td><input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[key]" value="<?php echo esc_attr( $field['key'] ); ?>" pattern="[a-z][a-z0-9_]{0,27}" maxlength="28" aria-label="<?php esc_attr_e( 'Key', 'lpg-commerce-core' ); ?>"></td>
			<td><input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $field['label'] ); ?>" aria-label="<?php esc_attr_e( 'Label', 'lpg-commerce-core' ); ?>"></td>
			<td>
				<select name="<?php echo esc_attr( $name ); ?>[type]" data-lpg-type aria-label="<?php esc_attr_e( 'Type', 'lpg-commerce-core' ); ?>">
					<?php foreach ( $types as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $field['type'], $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td><input type="text" class="small-text" name="<?php echo esc_attr( $name ); ?>[unit]" value="<?php echo esc_attr( $field['unit'] ); ?>" aria-label="<?php esc_attr_e( 'Unit', 'lpg-commerce-core' ); ?>"></td>
			<td><textarea rows="3" class="widefat" name="<?php echo esc_attr( $name ); ?>[options]" data-lpg-options aria-label="<?php esc_attr_e( 'Options', 'lpg-commerce-core' ); ?>"><?php echo esc_textarea( implode( "\n", (array) $field['options'] ) ); ?></textarea></td>
			<td><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[filterable]" value="1" <?php checked( ! empty( $field['filterable'] ) ); ?> aria-label="<?php esc_attr_e( 'Filterable', 'lpg-commerce-core' ); ?>"></td>
			<td><input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[help]" value="<?php echo esc_attr( $field['help'] ); ?>" aria-label="<?php esc_attr_e( 'Help text', 'lpg-commerce-core' ); ?>"></td>
			<td class="lpg-row-actions">
				<button type="button" class="button-link" data-lpg-move="up"><?php esc_html_e( 'Up', 'lpg-commerce-core' ); ?></button>
				<button type="button" class="button-link" data-lpg-move="down"><?php esc_html_e( 'Down', 'lpg-commerce-core' ); ?></button>
				<button type="button" class="button-link button-link-delete" data-lpg-remove><?php esc_html_e( 'Remove', 'lpg-commerce-core' ); ?></button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save handler for the profile edit screen.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function handle_save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || self::POST_TYPE !== $post->post_type ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Each value is validated and sanitised per field in ProfileRepository::validate_definitions().
		$rows = isset( $_POST['lpg_fields'] ) && is_array( $_POST['lpg_fields'] ) ? wp_unslash( $_POST['lpg_fields'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$errors = self::save_fields( $post_id, $rows );
		if ( $errors ) {
			set_transient( self::NOTICE_PREFIX . get_current_user_id() . '_' . $post_id, $errors, MINUTE_IN_SECONDS * 5 );
		}
	}

	/**
	 * Show rejected rows after a profile save (C1).
	 */
	public function render_notices(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || self::POST_TYPE !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display.
		if ( ! $post_id ) {
			return;
		}
		$key    = self::NOTICE_PREFIX . get_current_user_id() . '_' . $post_id;
		$errors = get_transient( $key );
		if ( ! is_array( $errors ) || ! $errors ) {
			return;
		}
		delete_transient( $key );
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Some field rows were not saved:', 'lpg-commerce-core' ) . '</p><ul>';
		foreach ( $errors as $error ) {
			echo '<li>' . esc_html( (string) $error ) . '</li>';
		}
		echo '</ul></div>';
	}

	/**
	 * Enqueue the row editor script on the profile edit screen.
	 *
	 * @param string $hook_suffix Admin page hook.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_script( 'lpg-spec-profile', LPG_COMMERCE_URL . 'assets/admin/spec-profile.js', array(), LPG_COMMERCE_VERSION, true );
		wp_enqueue_style( 'lpg-admin', LPG_COMMERCE_URL . 'assets/admin/admin.css', array(), LPG_COMMERCE_VERSION );
	}

	/**
	 * Default profile select on the "Add category" form.
	 */
	public function render_add_term_field(): void {
		wp_nonce_field( self::TERM_NONCE, self::TERM_FIELD, false );
		?>
		<div class="form-field term-lpg-profile-wrap">
			<label for="lpg-spec-profile-id"><?php esc_html_e( 'Default spec profile', 'lpg-commerce-core' ); ?></label>
			<?php $this->render_profile_select( 0 ); ?>
			<p><?php esc_html_e( 'Products in this category (or its subcategories) use this profile for technical specifications unless they set their own.', 'lpg-commerce-core' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Default profile select on the "Edit category" form.
	 *
	 * @param \WP_Term $term Term being edited.
	 */
	public function render_edit_term_field( \WP_Term $term ): void {
		?>
		<tr class="form-field term-lpg-profile-wrap">
			<th scope="row"><label for="lpg-spec-profile-id"><?php esc_html_e( 'Default spec profile', 'lpg-commerce-core' ); ?></label></th>
			<td>
				<?php
				wp_nonce_field( self::TERM_NONCE, self::TERM_FIELD, false );
				$this->render_profile_select( (int) get_term_meta( $term->term_id, ProfileResolver::TERM_META, true ) );
				?>
				<p class="description"><?php esc_html_e( 'Products in this category (or its subcategories) use this profile for technical specifications unless they set their own.', 'lpg-commerce-core' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Profile select element.
	 *
	 * @param int $current Selected profile ID.
	 */
	private function render_profile_select( int $current ): void {
		?>
		<select name="lpg_spec_profile_id" id="lpg-spec-profile-id">
			<option value="0"><?php esc_html_e( '— None (inherit from parent category) —', 'lpg-commerce-core' ); ?></option>
			<?php foreach ( self::profile_choices() as $id => $title ) : ?>
				<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( $current, $id ); ?>><?php echo esc_html( $title ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Save the category default profile.
	 *
	 * @param int $term_id Term ID.
	 */
	public function handle_term_save( int $term_id ): void {
		if ( ! isset( $_POST[ self::TERM_FIELD ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::TERM_FIELD ] ) ), self::TERM_NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_term', $term_id ) || ! isset( $_POST['lpg_spec_profile_id'] ) ) {
			return;
		}
		self::save_category_profile( $term_id, absint( wp_unslash( $_POST['lpg_spec_profile_id'] ) ) );
	}
}
