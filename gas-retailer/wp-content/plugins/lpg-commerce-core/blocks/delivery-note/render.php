<?php
/**
 * Server render for the `lpg/delivery-note` block. Logic lives in LPG\Commerce\Blocks\DeliveryNote.
 *
 * Provided by core: $attributes (array), $content (string), $block (WP_Block).
 *
 * @package LPG\Commerce
 */

defined( 'ABSPATH' ) || exit;

// The renderer escapes all of its output.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
echo \LPG\Commerce\Blocks\DeliveryNote::render( is_array( $attributes ?? null ) ? $attributes : array() );
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
