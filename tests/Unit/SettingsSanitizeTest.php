<?php
declare( strict_types=1 );

namespace CrumbSignal\Tests\Unit;

use Brain\Monkey\Functions;
use CrumbSignal_Settings;

final class SettingsSanitizeTest extends TestCase {
	protected function set_up(): void {
		parent::set_up();
		Functions\when( 'current_user_can' )->justReturn( true );
	}

	public function test_missing_checkboxes_are_treated_as_unchecked(): void {
		$output = CrumbSignal_Settings::sanitize( array() );

		foreach ( array( 'enabled', 'show_revisit', 'advanced_consent_mode', 'url_passthrough', 'ads_data_redaction', 'respect_gpc', 'respect_dnt' ) as $checkbox ) {
			$this->assertSame( 0, $output[ $checkbox ], "$checkbox should be 0 when absent, matching an unchecked HTML checkbox that the browser omits from the POST body." );
		}
	}

	public function test_checkboxes_coerce_truthy_input_to_one(): void {
		$output = CrumbSignal_Settings::sanitize( array( 'enabled' => 'on', 'respect_dnt' => '1' ) );

		$this->assertSame( 1, $output['enabled'] );
		$this->assertSame( 1, $output['respect_dnt'] );
	}

	public function test_non_checkbox_fields_fall_back_to_defaults_when_absent(): void {
		$defaults = CrumbSignal_Settings::defaults();
		$output   = CrumbSignal_Settings::sanitize( array() );

		$this->assertSame( $defaults['consent_duration'], $output['consent_duration'] );
		$this->assertSame( $defaults['position'], $output['position'] );
		$this->assertSame( $defaults['accent_color'], $output['accent_color'] );
		$this->assertSame( $defaults['heading'], $output['heading'] );
	}

	public function test_non_array_input_is_treated_as_empty(): void {
		$output = CrumbSignal_Settings::sanitize( 'not-an-array' );

		$this->assertSame( CrumbSignal_Settings::defaults()['position'], $output['position'] );
	}

	public function test_consent_duration_is_clamped_between_one_and_730_days(): void {
		// absint() takes the absolute value rather than rejecting negatives, so 0 (not a
		// negative number) is what actually exercises the lower bound.
		$this->assertSame( 1, CrumbSignal_Settings::sanitize( array( 'consent_duration' => 0 ) )['consent_duration'] );
		$this->assertSame( 730, CrumbSignal_Settings::sanitize( array( 'consent_duration' => 99999 ) )['consent_duration'] );
		$this->assertSame( 90, CrumbSignal_Settings::sanitize( array( 'consent_duration' => 90 ) )['consent_duration'] );
	}

	public function test_position_rejects_unknown_values(): void {
		$this->assertSame( 'bottom', CrumbSignal_Settings::sanitize( array( 'position' => 'top' ) )['position'] );
		$this->assertSame( 'bottom-left', CrumbSignal_Settings::sanitize( array( 'position' => 'bottom-left' ) )['position'] );
	}

	public function test_revisit_position_defaults_to_left_and_only_accepts_left_or_right(): void {
		$this->assertSame( 'left', CrumbSignal_Settings::defaults()['revisit_position'] );
		$this->assertSame( 'left', CrumbSignal_Settings::sanitize( array() )['revisit_position'] );
		$this->assertSame( 'right', CrumbSignal_Settings::sanitize( array( 'revisit_position' => 'right' ) )['revisit_position'] );
		$this->assertSame( 'left', CrumbSignal_Settings::sanitize( array( 'revisit_position' => 'top' ) )['revisit_position'] );
	}

	public function test_invalid_hex_color_falls_back_to_default(): void {
		$output = CrumbSignal_Settings::sanitize( array( 'accent_color' => 'not-a-color' ) );

		$this->assertSame( CrumbSignal_Settings::defaults()['accent_color'], $output['accent_color'] );
	}

	public function test_valid_hex_color_is_kept(): void {
		$output = CrumbSignal_Settings::sanitize( array( 'accent_color' => '#123abc' ) );

		$this->assertSame( '#123abc', $output['accent_color'] );
	}

	public function test_accept_button_text_color_defaults_to_white_and_is_validated(): void {
		$this->assertSame( '#ffffff', CrumbSignal_Settings::defaults()['button_text_color'] );
		$this->assertSame( '#ffffff', CrumbSignal_Settings::sanitize( array() )['button_text_color'], 'absent falls back to white' );
		$this->assertSame( '#000000', CrumbSignal_Settings::sanitize( array( 'button_text_color' => '#000000' ) )['button_text_color'] );
		$this->assertSame( '#ffffff', CrumbSignal_Settings::sanitize( array( 'button_text_color' => 'black' ) )['button_text_color'], 'a non-hex value falls back to white' );
	}

	public function test_gtm_id_is_validated_and_uppercased(): void {
		$output = CrumbSignal_Settings::sanitize( array( 'gtm_id' => 'gtm-abc123' ) );

		$this->assertSame( 'GTM-ABC123', $output['gtm_id'] );
	}

	public function test_invalid_gtm_id_is_rejected_and_reported(): void {
		Functions\expect( 'add_settings_error' )
			->once()
			->with( 'crumbsignal_settings', 'crumbsignal_gtm_id', \Mockery::type( 'string' ), 'error' );

		$output = CrumbSignal_Settings::sanitize( array( 'gtm_id' => 'not-valid' ) );

		$this->assertSame( '', $output['gtm_id'] );
	}

	public function test_ga4_and_google_ads_ids_are_validated(): void {
		$output = CrumbSignal_Settings::sanitize( array(
			'ga4_id'        => 'g-abcdef1234',
			'google_ads_id' => 'aw-123456789',
		) );

		$this->assertSame( 'G-ABCDEF1234', $output['ga4_id'] );
		$this->assertSame( 'AW-123456789', $output['google_ads_id'] );
	}

	public function test_arbitrary_code_settings_are_no_longer_accepted(): void {
		$output = CrumbSignal_Settings::sanitize( array(
			'custom_css' => 'body{color:red}',
			'scripts'    => array( array( 'category' => 'analytics', 'source' => 'https://example.com/a.js', 'inline' => 'alert(1);' ) ),
		) );

		$this->assertArrayNotHasKey( 'custom_css', $output );
		$this->assertArrayNotHasKey( 'scripts', $output );
		$this->assertArrayNotHasKey( 'custom_css', CrumbSignal_Settings::defaults() );
		$this->assertArrayNotHasKey( 'scripts', CrumbSignal_Settings::defaults() );
	}

	public function test_cookie_names_support_a_trailing_wildcard_and_are_deduplicated(): void {
		$output = CrumbSignal_Settings::sanitize( array(
			'cookies_analytics' => '_ga, _ga, _gid*, bad name!',
		) );

		$this->assertSame( '_ga, _gid*', $output['cookies_analytics'] );
	}

	public function test_cookie_names_are_sanitized_per_category(): void {
		$output = CrumbSignal_Settings::sanitize( array(
			'cookies_preferences' => 'theme_pref',
			'cookies_marketing'   => '<script>alert(1)</script>, _uetsid',
		) );

		$this->assertSame( 'theme_pref', $output['cookies_preferences'] );
		$this->assertSame( '', $output['cookies_analytics'] );
		$this->assertSame( '_uetsid', $output['cookies_marketing'] );
	}

	public function test_cookie_names_are_capped_at_twenty_five(): void {
		$names  = implode( ',', array_map( static fn( $i ) => 'cookie_' . $i, range( 1, 40 ) ) );
		$output = CrumbSignal_Settings::sanitize( array( 'cookies_analytics' => $names ) );

		$this->assertCount( 25, explode( ', ', $output['cookies_analytics'] ) );
	}

	public function test_non_string_cookie_names_are_ignored(): void {
		$output = CrumbSignal_Settings::sanitize( array( 'cookies_analytics' => array( '_ga' ) ) );

		$this->assertSame( '', $output['cookies_analytics'] );
	}
}
