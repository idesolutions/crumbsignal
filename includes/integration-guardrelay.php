<?php
/**
 * Integration with GuardRelay, a separate plugin that can say whether a visitor is a bot.
 *
 * @package CrumbSignal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tell GuardRelay that this site has a tag loader that can wait for its verdict on a visitor, so it does not warn
 * that bots will still be counted in analytics. Registered here, not in the frontend class, because the frontend
 * class does nothing in wp-admin, which is where GuardRelay shows its warning.
 *
 * @param array<string, string> $consumers Name => version of each tag loader that can honour the verdict.
 * @return array<string, string>
 */
function crumbsignal_register_gate_consumer( $consumers ) {
	$settings = apply_filters( 'crumbsignal_settings', crumbsignal_get_settings() );

	if ( ! empty( $settings['enabled'] ) && apply_filters( 'crumbsignal_gate_with_guardrelay', true ) ) {
		$consumers['CrumbSignal'] = CRUMBSIGNAL_VERSION;
	}

	return $consumers;
}
add_filter( 'guardrelay_gate_consumers', 'crumbsignal_register_gate_consumer' );
