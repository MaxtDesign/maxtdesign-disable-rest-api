<?php
/**
 * Uninstall handler.
 *
 * Removes all plugin data from the database when the plugin
 * is deleted through the WordPress admin interface.
 *
 * @package MaxtDesign\DisableRestApi
 * @since   1.0.0
 */

// Prevent direct access — must be called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove the single serialized settings option.
delete_option( 'mdra_settings' );

// For multisite: remove option from all sites.
if ( is_multisite() ) {
	$mdra_sites = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );

	foreach ( $mdra_sites as $mdra_site_id ) {
		switch_to_blog( (int) $mdra_site_id );
		delete_option( 'mdra_settings' );
		restore_current_blog();
	}
}
