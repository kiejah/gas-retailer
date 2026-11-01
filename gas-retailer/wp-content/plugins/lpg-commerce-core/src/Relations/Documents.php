<?php
/**
 * Product documents (`_lpg_documents`): datasheets, manuals, certificates as PDFs.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Relations;

use LPG\Commerce\Service;

defined( 'ABSPATH' ) || exit;

/**
 * §11.4: media-picker meta box; only application/pdf attachments are stored.
 */
final class Documents implements Service {

	public const META = '_lpg_documents';

	public const TYPES = array( 'datasheet', 'manual', 'certificate', 'other' );

	public const MIME = 'application/pdf';

	private const NONCE       = 'lpg_save_documents';
	private const NONCE_FIELD = 'lpg_documents_nonce';

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes_product', array( $this, 'add_meta_box' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Valid documents of a product: entries whose attachment still exists and is a PDF.
	 *
	 * @param int $product_id Product ID.
	 * @return array<int, array{attachment_id: int, type: string, label: string}>
	 */
	public static function get( int $product_id ): array {
		$stored = get_post_meta( $product_id, self::META, true );
		if ( ! is_array( $stored ) ) {
			return array();
		}
		$documents = array();
		foreach ( $stored as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = absint( $row['attachment_id'] ?? 0 );
			if ( ! self::is_pdf( $id ) || self::is_protected( $id ) ) {
				continue;
			}
			$type        = isset( $row['type'] ) && in_array( $row['type'], self::TYPES, true ) ? $row['type'] : 'other';
			$documents[] = array(
				'attachment_id' => $id,
				'type'          => $type,
				'label'         => isset( $row['label'] ) && is_scalar( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '',
			);
		}

		return $documents;
	}

	/**
	 * Sanitise submitted rows.
	 *
	 * Rows whose attachment is not an `application/pdf` attachment are dropped.
	 * Attachments not already on the product are only accepted when `$can_add`.
	 *
	 * @param array<int|string, mixed> $rows         Raw (unslashed) rows.
	 * @param int[]                    $existing_ids Attachment IDs already stored.
	 * @param bool                     $can_add      Whether new attachments may be added.
	 * @return array<int, array{attachment_id: int, type: string, label: string}>
	 */
	public static function sanitize_rows( array $rows, array $existing_ids, bool $can_add ): array {
		$clean = array();
		$seen  = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = absint( $row['attachment_id'] ?? 0 );
			if ( ! $id || isset( $seen[ $id ] ) || ! self::is_pdf( $id ) || self::is_protected( $id ) ) {
				continue;
			}
			if ( ! $can_add && ! in_array( $id, $existing_ids, true ) ) {
				continue;
			}
			$type        = isset( $row['type'] ) && is_string( $row['type'] ) && in_array( $row['type'], self::TYPES, true ) ? $row['type'] : 'other';
			$label       = isset( $row['label'] ) && is_scalar( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
			$seen[ $id ] = true;
			$clean[]     = array(
				'attachment_id' => $id,
				'type'          => $type,
				'label'         => $label,
			);
		}

		return $clean;
	}

	/**
	 * Apply submitted rows to a product object (not saved here).
	 *
	 * @param \WC_Product              $product Product.
	 * @param array<int|string, mixed> $rows    Raw (unslashed) rows.
	 * @param bool                     $can_add Whether new attachments may be added.
	 */
	public static function apply( \WC_Product $product, array $rows, bool $can_add ): void {
		$existing = array_map(
			static fn( array $doc ): int => $doc['attachment_id'],
			self::get( $product->get_id() )
		);
		$clean    = self::sanitize_rows( $rows, $existing, $can_add );
		if ( $clean ) {
			$product->update_meta_data( self::META, $clean );
		} else {
			$product->delete_meta_data( self::META );
		}
	}

	/**
	 * Whether an ID is an attachment with the PDF mime type.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function is_pdf( int $attachment_id ): bool {
		return $attachment_id > 0
			&& 'attachment' === get_post_type( $attachment_id )
			&& self::MIME === get_post_mime_type( $attachment_id );
	}

	/**
	 * Whether an attachment's file is in WooCommerce's protected downloads
	 * directory (`woocommerce_uploads/`), which must never be linked publicly (F1).
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function is_protected( int $attachment_id ): bool {
		$relative = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( 0 === strpos( ltrim( wp_normalize_path( $relative ), '/' ), 'woocommerce_uploads/' ) ) {
			return true;
		}
		$file = get_attached_file( $attachment_id, true );

		return is_string( $file ) && false !== strpos( wp_normalize_path( $file ), '/woocommerce_uploads/' );
	}

	/**
	 * Register the meta box.
	 */
	public function add_meta_box(): void {
		add_meta_box( 'lpg-documents', __( 'Product documents (PDF)', 'lpg-commerce-core' ), array( $this, 'render_meta_box' ), 'product', 'normal', 'default' );
	}

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Product post.
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE_FIELD, false );
		$documents = self::get( (int) $post->ID );
		?>
		<div data-lpg-documents>
			<table class="widefat striped lpg-documents-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'File', 'lpg-commerce-core' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'lpg-commerce-core' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Label', 'lpg-commerce-core' ); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'lpg-commerce-core' ); ?></span></th>
					</tr>
				</thead>
				<tbody data-lpg-doc-rows>
					<?php
					foreach ( $documents as $index => $document ) {
						$this->render_row( (string) $index, $document );
					}
					?>
				</tbody>
			</table>
			<p data-lpg-doc-empty <?php echo $documents ? 'hidden' : ''; ?>><?php esc_html_e( 'No documents yet.', 'lpg-commerce-core' ); ?></p>
			<template data-lpg-doc-template>
				<?php
				$this->render_row(
					'__INDEX__',
					array(
						'attachment_id' => 0,
						'type'          => 'datasheet',
						'label'         => '',
					)
				);
				?>
			</template>
			<?php if ( current_user_can( 'upload_files' ) ) : ?>
				<p><button type="button" class="button" data-lpg-doc-add data-title="<?php esc_attr_e( 'Choose PDF documents', 'lpg-commerce-core' ); ?>" data-button="<?php esc_attr_e( 'Add documents', 'lpg-commerce-core' ); ?>"><?php esc_html_e( 'Add PDF document', 'lpg-commerce-core' ); ?></button></p>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'Only PDF files are accepted. Use documents supplied by the manufacturer or supplier.', 'lpg-commerce-core' ); ?></p>
		</div>
		<?php
	}

	/**
	 * One document row.
	 *
	 * @param string               $index    Row index or placeholder.
	 * @param array<string, mixed> $document Document entry.
	 */
	private function render_row( string $index, array $document ): void {
		$name  = 'lpg_documents[' . $index . ']';
		$id    = (int) $document['attachment_id'];
		$file  = $id ? wp_basename( (string) get_attached_file( $id ) ) : '';
		$types = array(
			'datasheet'   => __( 'Datasheet', 'lpg-commerce-core' ),
			'manual'      => __( 'Manual', 'lpg-commerce-core' ),
			'certificate' => __( 'Certificate', 'lpg-commerce-core' ),
			'other'       => __( 'Other', 'lpg-commerce-core' ),
		);
		?>
		<tr data-lpg-doc-row data-id="<?php echo esc_attr( (string) $id ); ?>">
			<td>
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>[attachment_id]" value="<?php echo esc_attr( (string) $id ); ?>" data-lpg-doc-id>
				<?php if ( $id ) : ?>
					<a href="<?php echo esc_url( (string) wp_get_attachment_url( $id ) ); ?>" target="_blank" rel="noopener" data-lpg-doc-file><?php echo esc_html( $file ); ?></a>
				<?php else : ?>
					<span data-lpg-doc-file></span>
				<?php endif; ?>
			</td>
			<td>
				<select name="<?php echo esc_attr( $name ); ?>[type]" aria-label="<?php esc_attr_e( 'Document type', 'lpg-commerce-core' ); ?>">
					<?php foreach ( $types as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $document['type'], $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td><input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( (string) $document['label'] ); ?>" data-lpg-doc-label aria-label="<?php esc_attr_e( 'Label', 'lpg-commerce-core' ); ?>"></td>
			<td><button type="button" class="button-link button-link-delete" data-lpg-doc-remove><?php esc_html_e( 'Remove', 'lpg-commerce-core' ); ?></button></td>
		</tr>
		<?php
	}

	/**
	 * Save handler.
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
		// Each row is validated in sanitize_rows().
		$rows = isset( $_POST['lpg_documents'] ) && is_array( $_POST['lpg_documents'] ) ? wp_unslash( $_POST['lpg_documents'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		unset( $rows['__INDEX__'] );
		self::apply( $product, $rows, current_user_can( 'upload_files' ) );
	}

	/**
	 * Enqueue the media picker script on the product edit screen.
	 *
	 * @param string $hook_suffix Admin page hook.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}
		$deps = array();
		if ( current_user_can( 'upload_files' ) ) {
			wp_enqueue_media();
			$deps[] = 'media-editor';
		}
		wp_enqueue_script( 'lpg-documents', LPG_COMMERCE_URL . 'assets/admin/documents.js', $deps, LPG_COMMERCE_VERSION, true );
	}
}
