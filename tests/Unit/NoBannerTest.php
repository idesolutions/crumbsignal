<?php
declare( strict_types=1 );

namespace CrumbSignal\Tests\Unit;

use Brain\Monkey\Functions;
use CrumbSignal_Frontend;
use CrumbSignal_Settings;

if ( ! defined( 'CRUMBSIGNAL_VERSION' ) ) {
	define( 'CRUMBSIGNAL_VERSION', '9.9.9-test' );
}

require_once dirname( __DIR__, 2 ) . '/includes/class-crumbsignal-frontend.php';

/**
 * "Turn the banner off but keep my scripts running". The setting is phrased as "banner off" (default 0) on purpose:
 * a settings save that omits the key must leave the consent banner ON, never silently remove it.
 */
final class NoBannerTest extends TestCase {

	/** @var array<int, array<string, mixed>> */
	private $printed = array();

	protected function set_up(): void {
		parent::set_up();

		$this->printed = array();

		Functions\when( 'wp_print_inline_script_tag' )->alias(
			function ( $javascript, $attributes = array() ) {
				$this->printed[] = array( 'js' => $javascript, 'attributes' => $attributes );
			}
		);
		Functions\when( 'wp_print_script_tag' )->justReturn( null );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
	}

	/** @param array<string, mixed> $overrides */
	private function settings( array $overrides = array() ): array {
		return array_merge(
			CrumbSignal_Settings::defaults(),
			$overrides
		);
	}

	private function use_settings( array $settings ): void {
		$property = new \ReflectionProperty( CrumbSignal_Frontend::class, 'settings' );
		$property->setAccessible( true );
		$property->setValue( null, $settings );
	}

	// ---- The setting ---------------------------------------------------------------------------------

	public function test_the_banner_is_on_by_default(): void {
		$this->assertSame( 0, CrumbSignal_Settings::defaults()['no_banner'] );
	}

	public function test_a_save_that_omits_the_setting_leaves_the_banner_on(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$this->assertSame( 0, CrumbSignal_Settings::sanitize( array() )['no_banner'], 'absent means banner on' );
		$this->assertSame( 0, CrumbSignal_Settings::sanitize( array( 'enabled' => '1' ) )['no_banner'] );
		$this->assertSame( 1, CrumbSignal_Settings::sanitize( array( 'no_banner' => '1' ) )['no_banner'], 'only an explicit tick turns it off' );
	}

	public function test_an_existing_install_without_the_key_gets_the_banner(): void {
		$old = CrumbSignal_Settings::defaults();
		unset( $old['no_banner'] );

		$this->assertSame( 0, array_merge( CrumbSignal_Settings::defaults(), $old )['no_banner'] );
	}

	// ---- What the page prints ------------------------------------------------------------------------

	public function test_with_the_banner_on_the_page_gets_the_banner_and_its_script_config(): void {
		Functions\when( 'crumbsignal_get_settings' )->justReturn( $this->settings( array( 'enabled' => 1, 'no_banner' => 0 ) ) );
		Functions\when( 'is_admin' )->justReturn( false );

		CrumbSignal_Frontend::init();

		$this->assertNotFalse( has_action( 'wp_footer', array( CrumbSignal_Frontend::class, 'banner' ) ), 'banner is printed' );
		$this->assertNotFalse( has_action( 'wp_enqueue_scripts', array( CrumbSignal_Frontend::class, 'assets' ) ) );
	}

	public function test_with_the_banner_off_no_banner_is_printed_but_the_script_still_loads(): void {
		Functions\when( 'crumbsignal_get_settings' )->justReturn( $this->settings( array( 'enabled' => 1, 'no_banner' => 1 ) ) );
		Functions\when( 'is_admin' )->justReturn( false );

		CrumbSignal_Frontend::init();

		$this->assertFalse( has_action( 'wp_footer', array( CrumbSignal_Frontend::class, 'banner' ) ), 'no banner markup' );
		$this->assertNotFalse( has_action( 'wp_enqueue_scripts', array( CrumbSignal_Frontend::class, 'assets' ) ), 'the script still has to release the scripts' );
		$this->assertNotFalse( has_action( 'wp_head', array( CrumbSignal_Frontend::class, 'consent_bootstrap' ) ), 'and the consent defaults still have to be set' );
	}

	public function test_with_the_whole_plugin_disabled_nothing_is_registered(): void {
		Functions\when( 'crumbsignal_get_settings' )->justReturn( $this->settings( array( 'enabled' => 0, 'no_banner' => 1 ) ) );
		Functions\when( 'is_admin' )->justReturn( false );

		CrumbSignal_Frontend::init();

		$this->assertFalse( has_action( 'wp_head', array( CrumbSignal_Frontend::class, 'consent_bootstrap' ) ) );
	}

	public function test_the_bootstrap_tells_the_script_whether_the_banner_is_off(): void {
		foreach ( array( 0 => false, 1 => true ) as $flag => $expected ) {
			$this->printed = array();
			$this->use_settings( $this->settings( array( 'no_banner' => $flag ) ) );

			CrumbSignal_Frontend::consent_bootstrap();

			$this->assertStringContainsString( $expected ? '"noBanner":true' : '"noBanner":false', $this->printed[0]['js'] );
		}
	}

	public function test_the_bootstrap_defaults_to_granted_only_when_the_banner_is_off_and_still_honours_privacy_signals(): void {
		$this->use_settings( $this->settings() );

		CrumbSignal_Frontend::consent_bootstrap();

		$js = $this->printed[0]['js'];

		$this->assertStringContainsString( 'var fresh=c.noBanner?{necessary:true,preferences:true,analytics:true,marketing:true}:{necessary:true,preferences:false,analytics:false,marketing:false};', $js );
		$this->assertStringContainsString( 'var state=saved?saved.categories:fresh;', $js, 'a choice a visitor already made still wins' );
		$this->assertStringContainsString( 'if(rejected){state={necessary:true,preferences:false,analytics:false,marketing:false};}', $js, 'Global Privacy Control and Do Not Track still reject' );
		$this->assertStringContainsString( "consent_source:saved?'stored':(rejected?'privacy_signal':(c.noBanner?'no_banner':'default'))", $js, 'the dataLayer says why' );
	}
}
