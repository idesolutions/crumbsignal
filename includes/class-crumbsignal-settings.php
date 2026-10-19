<?php
/**
 * Settings and sanitization.
 *
 * @package CrumbSignal
 */

defined( 'ABSPATH' ) || exit;

final class CrumbSignal_Settings {
	/** @var string[] */
	const CATEGORIES = array( 'necessary', 'preferences', 'analytics', 'marketing' );

	/** @return void */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/** @return array<string, mixed> */
	public static function defaults() {
		return array(
			'enabled'                => 1,
			'no_banner'              => 0,
			'policy_url'             => '',
			'consent_duration'       => 180,
			'consent_version'        => 1,
			'position'               => 'bottom',
			'accent_color'           => '#6d4aff',
			'background_color'       => '#ffffff',
			'text_color'             => '#17151f',
			'button_text_color'      => '#ffffff',
			'show_revisit'           => 1,
			'revisit_position'       => 'left',
			'heading'                => __( 'Your privacy choices', 'crumbsignal' ),
			'description'            => __( 'We use cookies to improve your experience, understand site usage, and support our marketing. You can accept all cookies, reject optional cookies, or choose your preferences.', 'crumbsignal' ),
			'accept_label'           => __( 'Accept All', 'crumbsignal' ),
			'reject_label'           => __( 'Reject Optional', 'crumbsignal' ),
			'customize_label'        => __( 'Customize', 'crumbsignal' ),
			'save_label'             => __( 'Save Preferences', 'crumbsignal' ),
			'preferences_heading'    => __( 'Cookie preferences', 'crumbsignal' ),
			'necessary_description'  => __( 'Required for core website functions and security. These cannot be disabled.', 'crumbsignal' ),
			'preferences_description' => __( 'Remember choices that change how the website behaves or appears.', 'crumbsignal' ),
			'analytics_description'  => __( 'Help us understand how visitors use the website so we can improve it.', 'crumbsignal' ),
			'marketing_description'  => __( 'Used to measure campaigns and show more relevant advertising.', 'crumbsignal' ),
			'gtm_id'                 => '',
			'ga4_id'                 => '',
			'google_ads_id'          => '',
			'advanced_consent_mode'  => 0,
			'url_passthrough'        => 0,
			'ads_data_redaction'     => 1,
			'respect_gpc'            => 1,
			'respect_dnt'            => 0,
			'cookies_preferences'    => '',
			'cookies_analytics'      => '',
			'cookies_marketing'      => '',
		);
	}

	/** @return void */
	public static function register() {
		register_setting(
			'crumbsignal_settings_group',
			'crumbsignal_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize saved settings.
	 *
	 * @param mixed $input Incoming value.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$output   = $defaults;

		foreach ( array( 'enabled', 'no_banner', 'show_revisit', 'advanced_consent_mode', 'url_passthrough', 'ads_data_redaction', 'respect_gpc', 'respect_dnt' ) as $checkbox ) {
			$output[ $checkbox ] = empty( $input[ $checkbox ] ) ? 0 : 1;
		}

		$output['policy_url']       = esc_url_raw( $input['policy_url'] ?? '' );
		$output['consent_duration'] = max( 1, min( 730, absint( $input['consent_duration'] ?? 180 ) ) );
		$output['consent_version']  = max( 1, absint( $input['consent_version'] ?? 1 ) );
		$output['position']         = in_array( $input['position'] ?? '', array( 'bottom', 'bottom-left', 'bottom-right' ), true ) ? $input['position'] : 'bottom';
		$output['revisit_position'] = in_array( $input['revisit_position'] ?? '', array( 'left', 'right' ), true ) ? $input['revisit_position'] : 'left';

		foreach ( array( 'accent_color', 'background_color', 'text_color', 'button_text_color' ) as $color ) {
			$output[ $color ] = sanitize_hex_color( $input[ $color ] ?? '' ) ?: $defaults[ $color ];
		}

		$text_fields = array(
			'heading',
			'accept_label',
			'reject_label',
			'customize_label',
			'save_label',
			'preferences_heading',
			'gtm_id',
			'ga4_id',
			'google_ads_id',
		);
		foreach ( $text_fields as $field ) {
			$output[ $field ] = sanitize_text_field( $input[ $field ] ?? $defaults[ $field ] );
		}

		$textarea_fields = array(
			'description',
			'necessary_description',
			'preferences_description',
			'analytics_description',
			'marketing_description',
		);
		foreach ( $textarea_fields as $field ) {
			$output[ $field ] = sanitize_textarea_field( $input[ $field ] ?? $defaults[ $field ] );
		}

		$output['gtm_id']        = self::sanitize_tag_id( $output['gtm_id'], '/^GTM-[A-Z0-9]+$/i', 'gtm_id', __( 'The Google Tag Manager ID was not valid and was not saved.', 'crumbsignal' ) );
		$output['ga4_id']        = self::sanitize_tag_id( $output['ga4_id'], '/^G-[A-Z0-9]+$/i', 'ga4_id', __( 'The GA4 Measurement ID was not valid and was not saved.', 'crumbsignal' ) );
		$output['google_ads_id'] = self::sanitize_tag_id( $output['google_ads_id'], '/^AW-[0-9]+$/i', 'google_ads_id', __( 'The Google Ads ID was not valid and was not saved.', 'crumbsignal' ) );

		foreach ( array( 'cookies_preferences', 'cookies_analytics', 'cookies_marketing' ) as $field ) {
			$output[ $field ] = self::sanitize_cookie_names( $input[ $field ] ?? '' );
		}

		return $output;
	}

	/**
	 * Validate a Google tag identifier and show a useful settings error.
	 *
	 * @param string $value Value.
	 * @param string $pattern Validation expression.
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @return string
	 */
	private static function sanitize_tag_id( $value, $pattern, $code, $message ) {
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( $pattern, $value ) ) {
			add_settings_error( 'crumbsignal_settings', 'crumbsignal_' . $code, $message, 'error' );
			return '';
		}
		return strtoupper( $value );
	}

	/**
	 * Sanitize comma-separated first-party cookie names. A trailing wildcard
	 * is supported for services that suffix cookie names with an identifier.
	 *
	 * @param mixed $value Cookie names.
	 * @return string
	 */
	private static function sanitize_cookie_names( $value ) {
		$value = is_string( $value ) ? $value : '';
		$names = array();
		foreach ( array_slice( explode( ',', $value ), 0, 25 ) as $name ) {
			$name = trim( $name );
			if ( preg_match( '/^[A-Za-z0-9_.-]{1,100}\*?$/', $name ) ) {
				$names[] = $name;
			}
		}
		return implode( ', ', array_values( array_unique( $names ) ) );
	}
}
