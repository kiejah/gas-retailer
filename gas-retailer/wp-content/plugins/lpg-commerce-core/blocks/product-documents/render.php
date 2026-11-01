<?php
/**
 * Server render for the `lpg/product-documents` block. Logic lives in LPG\Commerce\Blocks\ProductDocuments.
 *
 * Provided by core: $attributes (array), $content (string), $block (WP_Block).
 *
 * @package LPG\Commerce
 */

defined( 'ABSPATH' ) || exit;

// The renderer escapes all of its output.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
echo \LPG\Commerce\Blocks\ProductDocuments::render(
	is_array( $attributes ?? null ) ? $attributes : array(),
	isset( $block->context['postId'] ) ? (int) $block->context['postId'] : null
);
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
