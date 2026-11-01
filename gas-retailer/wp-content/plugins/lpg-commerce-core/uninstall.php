<?php
/**
 * Uninstall routine.
 *
 * Removes plugin configuration and capabilities. Business data (spec profiles,
 * product specifications, segments; later RFQs and enquiries) is kept unless
 * the operator enabled "delete data on uninstall" in the plugin settings.
 * Global pa_* attributes are always kept: WooCommerce owns them (§11.10).
 *
 * @package LPG\Commerce
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$lpg_settings    = get_option( 'lpg_settings', array() );
$lpg_delete_data = is_array( $lpg_settings ) && ! empty( $lpg_settings['delete_data_on_uninstall'] );

if ( $lpg_delete_data ) {
	global $wpdb;

	// Spec profiles (the CPT is not registered during uninstall, so query directly).
	$lpg_profile_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'lpg_spec_profile' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( $lpg_profile_ids as $lpg_profile_id ) {
		wp_delete_post( (int) $lpg_profile_id, true );
	}

	// All _lpg_* post meta (spec values, overrides, compatibility, documents, warranty, flags).
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_lpg_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	// Category default profiles.
	delete_metadata( 'term', 0, 'lpg_spec_profile_id', '', true );

	// Segment terms (register the taxonomy briefly so the term API accepts it).
	if ( ! taxonomy_exists( 'lpg_segment' ) ) {
		register_taxonomy( 'lpg_segment', array( 'product' ) );
	}
	$lpg_segment_ids = get_terms(
		array(
			'taxonomy'   => 'lpg_segment',
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);
	if ( is_array( $lpg_segment_ids ) ) {
		foreach ( $lpg_segment_ids as $lpg_segment_id ) {
			wp_delete_term( (int) $lpg_segment_id, 'lpg_segment' );
		}
	}

	wp_cache_flush();
}//end if

// Capabilities are configuration: always removed.
$lpg_caps = array(
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
foreach ( array( 'administrator', 'shop_manager' ) as $lpg_role_name ) {
	$lpg_role = get_role( $lpg_role_name );
	if ( $lpg_role ) {
		foreach ( $lpg_caps as $lpg_cap ) {
			$lpg_role->remove_cap( $lpg_cap );
		}
	}
}

delete_option( 'lpg_settings' );
delete_option( 'lpg_commerce_version' );
