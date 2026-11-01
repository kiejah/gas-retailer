<?php
/**
 * Renderer for the `lpg/product-documents` block.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Blocks;

use LPG\Commerce\Relations\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * §11.9: a list of PDF links with type and file size.
 */
final class ProductDocuments {

	public const NAME = 'lpg/product-documents';

	public const DEFAULT_HEADING = 'Product documents';

	/**
	 * Render the block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param int|null             $product_id Product ID from block context.
	 */
	public static function render( array $attributes, ?int $product_id ): string {
		$product = Markup::product( $product_id );
		if ( ! $product ) {
			return '';
		}

		$items = '';
		foreach ( Documents::get( $product->get_id() ) as $document ) {
			$items .= self::item( $document );
		}
		if ( '' === $items ) {
			return '';
		}

		$heading = Markup::heading( $attributes, self::DEFAULT_HEADING, __( 'Product documents', 'lpg-commerce-core' ) );

		return sprintf(
			'<section %1$s>%2$s<ul class="lpg-product-documents__list">%3$s</ul></section>',
			Markup::wrapper( self::NAME, 'lpg-product-documents' ),
			'' !== $heading ? '<h2 class="lpg-product-documents__heading">' . esc_html( $heading ) . '</h2>' : '',
			$items
		);
	}

	/**
	 * Translated document type labels.
	 *
	 * @return array<string, string>
	 */
	public static function type_labels(): array {
		return array(
			'datasheet'   => __( 'Datasheet', 'lpg-commerce-core' ),
			'manual'      => __( 'Manual', 'lpg-commerce-core' ),
			'certificate' => __( 'Certificate', 'lpg-commerce-core' ),
			'other'       => __( 'Document', 'lpg-commerce-core' ),
		);
	}

	/**
	 * One list item, or '' when the attachment has no URL.
	 *
	 * @param array{attachment_id: int, type: string, label: string} $document Document entry.
	 */
	private static function item( array $document ): string {
		$id  = (int) $document['attachment_id'];
		$url = wp_get_attachment_url( $id );
		if ( ! $url ) {
			return '';
		}

		$label = '' !== $document['label'] ? $document['label'] : get_the_title( $id );
		if ( '' === trim( (string) $label ) ) {
			$label = wp_basename( (string) get_attached_file( $id ) );
		}
		$types = self::type_labels();
		$meta  = array( $types[ $document['type'] ] ?? $types['other'], 'PDF' );
		$size  = self::file_size( $id );
		if ( $size ) {
			$meta[] = $size;
		}

		return sprintf(
			'<li class="lpg-product-documents__item lpg-product-documents__item--%1$s"><a class="lpg-product-documents__link" href="%2$s" target="_blank" rel="noopener"><span class="lpg-product-documents__label">%3$s</span> <span class="lpg-product-documents__meta">(%4$s)</span><span class="screen-reader-text"> %5$s</span></a></li>',
			esc_attr( sanitize_html_class( $document['type'] ) ),
			esc_url( $url ),
			esc_html( (string) $label ),
			esc_html( implode( ', ', $meta ) ),
			esc_html__( '(opens in a new tab)', 'lpg-commerce-core' )
		);
	}

	/**
	 * Human file size from attachment metadata, falling back to the file.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	private static function file_size( int $attachment_id ): string {
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$bytes    = is_array( $metadata ) && isset( $metadata['filesize'] ) ? (int) $metadata['filesize'] : 0;
		if ( ! $bytes ) {
			$file  = get_attached_file( $attachment_id );
			$bytes = is_string( $file ) && is_readable( $file ) ? (int) filesize( $file ) : 0;
		}
		$formatted = $bytes > 0 ? size_format( $bytes ) : false;

		return is_string( $formatted ) ? $formatted : '';
	}
}
