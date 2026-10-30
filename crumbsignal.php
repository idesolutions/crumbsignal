<?php
/*
Plugin Name: CrumbSignal
Plugin URI: https://idesolutions.io/products/crumbsignal/
Description: Privacy-first cookie consent, script controls, Google Consent Mode v2, and data layer signals for WordPress.
Version: 1.0.0
Author: IDE Solutions LLC
Author URI: https://idesolutions.io/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 6.4
Requires PHP: 8.0
Text Domain: crumbsignal
Domain Path: /languages

CrumbSignal is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

CrumbSignal is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with CrumbSignal. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

defined( 'ABSPATH' ) || exit;

define( 'CRUMBSIGNAL_VERSION', '1.0.0' );
define( 'CRUMBSIGNAL_FILE', __FILE__ );
define( 'CRUMBSIGNAL_PATH', plugin_dir_path( __FILE__ ) );
define( 'CRUMBSIGNAL_URL', plugin_dir_url( __FILE__ ) );

require_once CRUMBSIGNAL_PATH . 'includes/class-crumbsignal-settings.php';
require_once CRUMBSIGNAL_PATH . 'includes/class-crumbsignal-admin.php';
require_once CRUMBSIGNAL_PATH . 'includes/class-crumbsignal-frontend.php';

/**
 * Return sanitized plugin settings merged with defaults.
 *
 * @return array<string, mixed>
 */
function crumbsignal_get_settings() {
	$saved = get_option( 'crumbsignal_settings', array() );
	$defaults = CrumbSignal_Settings::defaults();

	// Only known keys survive: settings saved by earlier builds (such as raw scripts or custom CSS) are ignored.
	return array_intersect_key( wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults ), $defaults );
}

/**
 * Bootstrap CrumbSignal.
 *
 * @return void
 */
function crumbsignal_boot() {
	CrumbSignal_Settings::init();
	CrumbSignal_Admin::init();
	CrumbSignal_Frontend::init();
	add_shortcode( 'crumbsignal_preferences', array( 'CrumbSignal_Frontend', 'preferences_link' ) );
}
add_action( 'plugins_loaded', 'crumbsignal_boot' );

register_activation_hook(
	__FILE__,
	static function () {
		if ( false === get_option( 'crumbsignal_settings', false ) ) {
			add_option( 'crumbsignal_settings', CrumbSignal_Settings::defaults(), '', false );
		}
	}
);
