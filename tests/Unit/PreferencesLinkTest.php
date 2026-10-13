<?php
declare( strict_types=1 );

namespace CrumbSignal\Tests\Unit;

use Brain\Monkey\Functions;
use CrumbSignal_Frontend;

require_once dirname( __DIR__, 2 ) . '/includes/class-crumbsignal-frontend.php';

/**
 * [crumbsignal_preferences]: the footer link that reopens the preferences popup.
 */
final class PreferencesLinkTest extends TestCase {

	protected function set_up(): void {
		parent::set_up();

		Functions\when( 'shortcode_atts' )->alias(
			function ( $pairs, $atts ) {
				return array_merge( $pairs, (array) $atts );
			}
		);
		Functions\when( 'esc_html' )->alias(
			function ( $text ) {
				return htmlspecialchars( (string) $text, ENT_QUOTES );
			}
		);
	}

	/** @param array<string, mixed> $settings */
	private function use_settings( array $settings ): void {
		$property = new \ReflectionProperty( CrumbSignal_Frontend::class, 'settings' );
		$property->setAccessible( true );
		$property->setValue( null, $settings );
	}

	public function test_it_prints_a_link_that_the_script_recognises(): void {
		$this->use_settings( array( 'enabled' => 1, 'no_banner' => 0 ) );

		$this->assertSame(
			'<a href="#crumbsignal-preferences" class="crumbsignal-open-preferences">Cookie Preferences</a>',
			CrumbSignal_Frontend::preferences_link( '' )
		);
	}

	public function test_the_label_can_be_changed_and_is_escaped(): void {
		$this->use_settings( array( 'enabled' => 1, 'no_banner' => 0 ) );

		$this->assertStringContainsString(
			'>Your &lt;b&gt;choices&lt;/b&gt;</a>',
			CrumbSignal_Frontend::preferences_link( array( 'label' => 'Your <b>choices</b>' ) )
		);
	}

	public function test_it_prints_nothing_when_there_is_no_banner_to_open(): void {
		$this->use_settings( array( 'enabled' => 0, 'no_banner' => 0 ) );
		$this->assertSame( '', CrumbSignal_Frontend::preferences_link( '' ), 'plugin disabled' );

		$this->use_settings( array( 'enabled' => 1, 'no_banner' => 1 ) );
		$this->assertSame( '', CrumbSignal_Frontend::preferences_link( '' ), 'banner switched off' );
	}

	public function test_the_shortcode_is_registered_when_the_plugin_boots(): void {
		$main = (string) file_get_contents( dirname( __DIR__, 2 ) . '/crumbsignal.php' );

		$this->assertStringContainsString( "add_shortcode( 'crumbsignal_preferences', array( 'CrumbSignal_Frontend', 'preferences_link' ) );", $main );
	}
}
