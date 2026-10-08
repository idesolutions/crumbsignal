<?php
/**
 * Remove CrumbSignal data when the plugin is deleted.
 *
 * @package CrumbSignal
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( is_multisite() ) {
	$crumbsignal_sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $crumbsignal_sites as $crumbsignal_site_id ) {
		switch_to_blog( $crumbsignal_site_id );
		delete_option( 'crumbsignal_settings' );
		restore_current_blog();
	}
} else {
	delete_option( 'crumbsignal_settings' );
}
