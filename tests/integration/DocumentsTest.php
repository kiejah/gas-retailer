<?php
/**
 * Product documents: PDFs only (§11.4).
 *
 * @package LPG\Commerce\Tests
 */

require_once __DIR__ . '/LpgFixtures.php';

use LPG\Commerce\Relations\Documents;

class DocumentsTest extends WP_UnitTestCase {

	use LpgFixtures;

	private function attachment( string $mime, string $file ): int {
		return self::factory()->attachment->create_object(
			array(
				'file'           => $file,
				'post_mime_type' => $mime,
				'post_title'     => $file,
			)
		);
	}

	public function test_rejects_non_pdf_attachments_and_sanitises(): void {
		$pdf   = $this->attachment( 'application/pdf', 'datasheet.pdf' );
		$image = $this->attachment( 'image/jpeg', 'photo.jpg' );
		$post  = self::factory()->post->create();

		$rows = Documents::sanitize_rows(
			array(
				array(
					'attachment_id' => (string) $pdf,
					'type'          => 'datasheet',
					'label'         => '<b>Datasheet</b>',
				),
				array(
					'attachment_id' => $image,
					'type'          => 'manual',
					'label'         => 'Photo',
				),
				array(
					'attachment_id' => $post,
					'type'          => 'manual',
					'label'         => 'Post',
				),
				array(
					'attachment_id' => $pdf,
					'type'          => 'manual',
					'label'         => 'Duplicate',
				),
			),
			array(),
			true
		);

		$this->assertSame(
			array(
				array(
					'attachment_id' => $pdf,
					'type'          => 'datasheet',
					'label'         => 'Datasheet',
				),
			),
			$rows
		);
	}

	public function test_unknown_type_becomes_other(): void {
		$pdf  = $this->attachment( 'application/pdf', 'x.pdf' );
		$rows = Documents::sanitize_rows(
			array(
				array(
					'attachment_id' => $pdf,
					'type'          => 'brochure',
				),
			),
			array(),
			true
		);

		$this->assertSame( 'other', $rows[0]['type'] );
	}

	public function test_adding_requires_upload_capability(): void {
		$existing = $this->attachment( 'application/pdf', 'old.pdf' );
		$new      = $this->attachment( 'application/pdf', 'new.pdf' );

		$rows = Documents::sanitize_rows(
			array(
				array( 'attachment_id' => $existing ),
				array( 'attachment_id' => $new ),
			),
			array( $existing ),
			false
		);

		$this->assertSame( array( $existing ), wp_list_pluck( $rows, 'attachment_id' ) );
	}

	public function test_get_returns_valid_entries_only(): void {
		$pdf     = $this->attachment( 'application/pdf', 'manual.pdf' );
		$deleted = $this->attachment( 'application/pdf', 'gone.pdf' );
		$image   = $this->attachment( 'image/png', 'img.png' );
		$product = $this->create_product();
		update_post_meta(
			$product->get_id(),
			Documents::META,
			array(
				array(
					'attachment_id' => $pdf,
					'type'          => 'manual',
					'label'         => 'Manual',
				),
				array(
					'attachment_id' => $deleted,
					'type'          => 'manual',
					'label'         => 'Gone',
				),
				array(
					'attachment_id' => $image,
					'type'          => 'other',
					'label'         => 'Image',
				),
				'garbage',
			)
		);
		wp_delete_attachment( $deleted, true );

		$this->assertSame(
			array(
				array(
					'attachment_id' => $pdf,
					'type'          => 'manual',
					'label'         => 'Manual',
				),
			),
			Documents::get( $product->get_id() )
		);
	}

	public function test_get_skips_protected_downloads_and_sanitises_label(): void {
		$protected = $this->attachment( 'application/pdf', 'woocommerce_uploads/2026/11/secret.pdf' );
		$public    = $this->attachment( 'application/pdf', '2026/11/manual.pdf' );
		$product   = $this->create_product();
		update_post_meta(
			$product->get_id(),
			Documents::META,
			array(
				array(
					'attachment_id' => $protected,
					'type'          => 'manual',
					'label'         => 'Secret',
				),
				array(
					'attachment_id' => $public,
					'type'          => 'manual',
					'label'         => '<script>alert(1)</script>Manual <b>v2</b>',
				),
			)
		);

		$this->assertTrue( Documents::is_protected( $protected ) );
		$this->assertFalse( Documents::is_protected( $public ) );
		$this->assertSame(
			array(
				array(
					'attachment_id' => $public,
					'type'          => 'manual',
					'label'         => 'Manual v2',
				),
			),
			Documents::get( $product->get_id() )
		);
		$this->assertSame( array(), Documents::sanitize_rows( array( array( 'attachment_id' => $protected ) ), array(), true ), 'Protected files cannot be added either.' );
	}

	public function test_apply_stores_on_product(): void {
		$pdf     = $this->attachment( 'application/pdf', 'cert.pdf' );
		$product = $this->create_product();

		Documents::apply(
			$product,
			array(
				array(
					'attachment_id' => $pdf,
					'type'          => 'certificate',
					'label'         => 'Certificate',
				),
			),
			true
		);
		$product->save();
		$this->assertCount( 1, Documents::get( $product->get_id() ) );

		Documents::apply( $product, array(), true );
		$product->save();
		$this->assertFalse( metadata_exists( 'post', $product->get_id(), Documents::META ) );
	}
}
