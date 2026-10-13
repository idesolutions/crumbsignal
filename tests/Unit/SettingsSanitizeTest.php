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
		$this->assertSame( array(), $output['scripts'] );
	}

	public function test_non_array_input_is_treated_as_empty(): void {
		$output = CrumbSignal_Settings::sanitize( 'not-an-array' );

		$this->assertSame( CrumbSignal_Settings::defaults()['position'], $output['position'] );
		$this->assertSame( array(), $output['scripts'] );
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

	public function test_custom_css_strips_import_and_url_constructs(): void {
		Functions\when( 'add_settings_error' )->justReturn( null );

		$output = CrumbSignal_Settings::sanitize( array(
			'custom_css' => 'body{color:red} @import url(evil.css); .x{background:url(javascript:alert(1))}',
		) );

		$this->assertStringNotContainsString( '@import', $output['custom_css'] );
		$this->assertStringNotContainsString( 'url(', $output['custom_css'] );
	}

	public function test_custom_css_allows_ordinary_rules_untouched(): void {
		$css    = 'body{color:red;font-size:14px}';
		$output = CrumbSignal_Settings::sanitize( array( 'custom_css' => $css ) );

		$this->assertSame( $css, $output['custom_css'] );
	}

	public function test_scripts_require_a_known_category(): void {
		$output = CrumbSignal_Settings::sanitize( array(
			'scripts' => array(
				array(
					'category' => 'unknown',
					'source'   => 'https://example.com/a.js',
				),
			),
		) );

		$this->assertSame( array(), $output['scripts'] );
	}

	public function test_scripts_require_a_source_or_inline_body(): void {
		$output = CrumbSignal_Settings::sanitize( array(
			'scripts' => array(
				array(
					'category' => 'analytics',
					'source'   => '',
					'inline'   => '',
				),
			),
		) );

		$this->assertSame( array(), $output['scripts'] );
	}

	public function test_valid_external_script_is_kept(): void {
		$output = CrumbSignal_Settings::sanitize( array(
			'scripts' => array(
				array(
					'category' => 'analytics',
					'source'   => 'https://example.com/a.js',
					'enabled'  => 1,
				),
			),
		) );

		$this->assertCount( 1, $output['scripts'] );
		$this->assertSame( 'analytics', $output['scripts'][0]['category'] );
		$this->assertSame( 'https://example.com/a.js', $output['scripts'][0]['source'] );
	}

	public function test_inline_script_is_dropped_without_unfiltered_html_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$output = CrumbSignal_Settings::sanitize( array(
			'scripts' => array(
				array(
					'category' => 'analytics',
					'inline'   => 'console.log(1);',
				),
			),
		) );

		$this->assertSame( '', $output['scripts'][0]['inline'] );
	}

	public function test_inline_script_is_kept_with_unfiltered_html_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$output = CrumbSignal_Settings::sanitize( array(
			'scripts' => array(
				array(
					'category' => 'analytics',
					'inline'   => 'console.log(1);',
				),
			),
		) );

		$this->assertSame( 'console.log(1);', $output['scripts'][0]['inline'] );
	}

	public function test_inline_script_with_a_closing_script_tag_is_rejected(): void {
		Functions\when( 'add_settings_error' )->justReturn( null );

		$output = CrumbSignal_Settings::sanitize( array(
			'scripts' => array(
				array(
					'category' => 'analytics',
					'inline'   => '</script><script>alert(1)</script>',
				),
			),
		) );

		$this->assertSame( array(), $output['scripts'] );
	}

	public function test_scripts_are_capped_at_fifty_rows(): void {
		Functions\when( 'add_settings_error' )->justReturn( null );

		$rows = array();
		for ( $i = 0; $i < 60; $i++ ) {
			$rows[] = array(
				'category' => 'analytics',
				'source'   => 'https://example.com/' . $i . '.js',
			);
		}

		$output = CrumbSignal_Settings::sanitize( array( 'scripts' => $rows ) );

		$this->assertCount( 50, $output['scripts'] );
	}

	public function test_exceeding_the_script_cap_reports_an_error(): void {
		Functions\expect( 'add_settings_error' )
			->once()
			->with( 'crumbsignal_settings', 'crumbsignal_scripts_limit', \Mockery::type( 'string' ), 'error' );

		$rows = array();
		for ( $i = 0; $i < 51; $i++ ) {
			$rows[] = array(
				'category' => 'analytics',
				'source'   => 'https://example.com/' . $i . '.js',
			);
		}

		$output = CrumbSignal_Settings::sanitize( array( 'scripts' => $rows ) );

		$this->assertCount( 50, $output['scripts'] );
	}

	public function test_exactly_fifty_scripts_does_not_report_an_error(): void {
		Functions\expect( 'add_settings_error' )->never();

		$rows = array();
		for ( $i = 0; $i < 50; $i++ ) {
			$rows[] = array(
				'category' => 'analytics',
				'source'   => 'https://example.com/' . $i . '.js',
			);
		}

		$output = CrumbSignal_Settings::sanitize( array( 'scripts' => $rows ) );

		$this->assertCount( 50, $output['scripts'] );
	}

	public function test_cookie_names_support_a_trailing_wildcard_and_are_deduplicated(): void {
		$output = CrumbSignal_Settings::sanitize( array(
			'scripts' => array(
				array(
					'category' => 'analytics',
					'source'   => 'https://example.com/a.js',
					'cookies'  => '_ga, _ga, _gid*, bad name!',
				),
			),
		) );

		$this->assertSame( '_ga, _gid*', $output['scripts'][0]['cookies'] );
	}
}
