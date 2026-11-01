<?php
/**
 * Uninstall routine.
 *
 * Removes plugin configuration. Business data (RFQs, enquiries, product
 * specifications) is kept unless the operator enabled
 * "delete data on uninstall" in the plugin settings.
 *
 * @package LPG\Commerce
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$lpg_settings    = get_option( 'lpg_settings', array() );
$lpg_delete_data = is_array( $lpg_settings ) && ! empty( $lpg_settings['delete_data_on_uninstall'] );

if ( $lpg_delete_data ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf -- Filled in as business data types are added (Phases 3 and 5).
}

delete_option( 'lpg_settings' );
delete_option( 'lpg_commerce_version' );
