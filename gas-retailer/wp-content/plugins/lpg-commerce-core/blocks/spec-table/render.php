<?php
/**
 * Server render for the `lpg/spec-table` block. Logic lives in LPG\Commerce\Blocks\SpecTable.
 *
 * Provided by core: $attributes (array), $content (string), $block (WP_Block).
 *
 * @package LPG\Commerce
 */

defined( 'ABSPATH' ) || exit;

// The renderer escapes all of its output.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
echo \LPG\Commerce\Blocks\SpecTable::render(
	is_array( $attributes ?? null ) ? $attributes : array(),
	isset( $block->context['postId'] ) ? (int) $block->context['postId'] : null
);
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
