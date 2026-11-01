<?php
/**
 * Uninstall routine.
 *
 * Removes plugin configuration, capabilities and the retention cron event.
 * Business data (spec profiles, product specifications, segments, cylinder
 * brands, quote requests and enquiries) is kept unless the operator enabled
 * "delete data on uninstall" in the plugin settings.
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

	// Quote requests and enquiries (CPTs not registered during uninstall).
	$lpg_record_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ( 'lpg_rfq', 'lpg_enquiry' )" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( $lpg_record_ids as $lpg_record_id ) {
		wp_delete_post( (int) $lpg_record_id, true );
	}

	// Cylinder brands and their sizes.
	if ( ! taxonomy_exists( 'lpg_cylinder_brand' ) ) {
		register_taxonomy( 'lpg_cylinder_brand', array() );
	}
	$lpg_brand_ids = get_terms(
		array(
			'taxonomy'   => 'lpg_cylinder_brand',
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);
	if ( is_array( $lpg_brand_ids ) ) {
		foreach ( $lpg_brand_ids as $lpg_brand_id ) {
			wp_delete_term( (int) $lpg_brand_id, 'lpg_cylinder_brand' );
		}
	}
	delete_metadata( 'term', 0, 'lpg_sizes', '', true );

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
	'edit_lpg_rfqs',
	'edit_others_lpg_rfqs',
	'edit_private_lpg_rfqs',
	'edit_published_lpg_rfqs',
	'publish_lpg_rfqs',
	'read_private_lpg_rfqs',
	'delete_lpg_rfqs',
	'delete_others_lpg_rfqs',
	'delete_private_lpg_rfqs',
	'delete_published_lpg_rfqs',
	'edit_lpg_enquiries',
	'edit_others_lpg_enquiries',
	'edit_private_lpg_enquiries',
	'edit_published_lpg_enquiries',
	'publish_lpg_enquiries',
	'read_private_lpg_enquiries',
	'delete_lpg_enquiries',
	'delete_others_lpg_enquiries',
	'delete_private_lpg_enquiries',
	'delete_published_lpg_enquiries',
);
foreach ( array( 'administrator', 'shop_manager' ) as $lpg_role_name ) {
	$lpg_role = get_role( $lpg_role_name );
	if ( $lpg_role ) {
		foreach ( $lpg_caps as $lpg_cap ) {
			$lpg_role->remove_cap( $lpg_cap );
		}
	}
}

// Scheduled retention clean-up and rate-limit transients.
global $wpdb;
wp_clear_scheduled_hook( 'lpg_retention_cleanup' );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_lpg_rl_' ) . '%', $wpdb->esc_like( '_transient_timeout_lpg_rl_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

delete_option( 'lpg_settings' );
delete_option( 'lpg_commerce_version' );
