<?php
declare( strict_types=1 );

namespace CrumbSignal\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;

require_once dirname( __DIR__, 2 ) . '/includes/class-crumbsignal-frontend.php';

/**
 * The GuardRelay gate, server side: on a page that listens for GuardRelay, Google tags that advanced mode would
 * load at once are printed inert instead (the script releases them to anyone who is not a bot), and the page
 * tells the script it is listening. Everywhere else the output must be exactly what it was before.
 */
final class FrontendGateTest extends TestCase {

	/** @var string[] What the print functions were asked to print, as JSON lines. */
	private $printed = array();

	protected function set_up(): void {
		parent::set_up();

		$this->printed = array();

		Functions\when( 'wp_print_script_tag' )->alias(
			function ( $attributes ) {
				$this->printed[] = array( 'tag' => 'script', 'attributes' => $attributes );
			}
		);
		Functions\when( 'wp_print_inline_script_tag' )->alias(
			function ( $javascript, $attributes = array() ) {
				$this->printed[] = array( 'tag' => 'inline', 'js' => $javascript, 'attributes' => $attributes );
			}
		);
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
	}

	/** @param array<string, mixed> $settings Overrides on top of a minimal set. */
	private function use_settings( array $settings ): void {
		$property = new \ReflectionProperty( \CrumbSignal_Frontend::class, 'settings' );
		$property->setAccessible( true );
		$property->setValue(
			null,
			array_merge(
				array(
					'gtm_id'                => '',
					'ga4_id'                => '',
					'google_ads_id'         => '',
					'advanced_consent_mode' => 0,
					'consent_version'       => 1,
					'consent_duration'      => 180,
					'ads_data_redaction'    => 0,
					'url_passthrough'       => 0,
					'respect_gpc'           => 1,
					'respect_dnt'           => 0,
				),
				$settings
			)
		);
	}

	private function call( string $method ) {
		$reflection = new \ReflectionMethod( \CrumbSignal_Frontend::class, $method );
		$reflection->setAccessible( true );

		return $reflection->invoke( null );
	}

	private function gate( bool $on ): void {
		Filters\expectApplied( 'crumbsignal_gate_with_guardrelay' )->andReturn( $on );
	}

	/** @return array<int, array<string, mixed>> */
	private function tags(): array {
		return $this->printed;
	}

	// ---- Without the gate: output is unchanged -------------------------------------------------------

	public function test_without_guardrelay_the_gate_is_off(): void {
		$this->assertFalse( function_exists( 'guardrelay_analytics_gate_active' ), 'this test must run before any test defines the helper' );
		$this->use_settings( array() );

		$this->assertFalse( $this->call( 'gate_active' ) );
	}

	public function test_advanced_mode_without_the_gate_still_loads_gtag_live(): void {
		$this->use_settings( array( 'advanced_consent_mode' => 1, 'ga4_id' => 'G-TEST123' ) );
		$this->gate( false );

		$this->call( 'google_tags' );

		$tags = $this->tags();

		$this->assertCount( 2, $tags );
		$this->assertSame( array( 'async' => true, 'src' => 'https://www.googletagmanager.com/gtag/js?id=G-TEST123' ), $tags[0]['attributes'] );
		$this->assertSame( array( 'id' => 'crumbsignal-google-tags' ), $tags[1]['attributes'] );
		$this->assertStringContainsString( "gtag('config',\"G-TEST123\")", $tags[1]['js'] );
	}

	public function test_advanced_mode_without_the_gate_still_loads_gtm_live(): void {
		$this->use_settings( array( 'advanced_consent_mode' => 1, 'gtm_id' => 'GTM-ABC123' ) );
		$this->gate( false );

		$this->call( 'google_tags' );

		$this->assertSame( array( 'id' => 'crumbsignal-gtm' ), $this->tags()[0]['attributes'] );
	}

	/** @return array<string, array{0: bool}> */
	public static function gate_values(): array {
		return array(
			'gate off' => array( false ),
			'gate on'  => array( true ),
		);
	}

	/** @dataProvider gate_values */
	public function test_strict_mode_is_unchanged_whether_or_not_the_gate_is_on( bool $gate ): void {
		$this->use_settings( array( 'advanced_consent_mode' => 0, 'gtm_id' => 'GTM-ABC123' ) );
		$this->gate( $gate );

		$this->call( 'google_tags' );

		$this->assertSame(
			array( 'id' => 'crumbsignal-gtm', 'type' => 'text/plain', 'data-crumbsignal-category' => 'google' ),
			$this->tags()[0]['attributes'],
			'strict mode already holds the tag until consent'
		);
	}

	public function test_strict_gtag_is_unchanged_with_the_gate_on(): void {
		$this->use_settings( array( 'advanced_consent_mode' => 0, 'ga4_id' => 'G-TEST123' ) );
		$this->gate( true );

		$this->call( 'google_tags' );

		foreach ( $this->tags() as $tag ) {
			$this->assertArrayNotHasKey( 'data-crumbsignal-gate', $tag['attributes'], 'strict mode needs no gate markup' );
			$this->assertSame( 'text/plain', $tag['attributes']['type'] );
		}
	}

	// ---- With the gate: advanced mode holds the tags -------------------------------------------------

	public function test_advanced_gtag_is_held_inert_when_the_gate_is_on(): void {
		$this->use_settings( array( 'advanced_consent_mode' => 1, 'ga4_id' => 'G-TEST123' ) );
		$this->gate( true );

		$this->call( 'google_tags' );

		$tags = $this->tags();

		$this->assertCount( 2, $tags );
		$this->assertSame(
			array( 'data-src' => 'https://www.googletagmanager.com/gtag/js?id=G-TEST123', 'type' => 'text/plain', 'data-crumbsignal-gate' => 'verdict' ),
			$tags[0]['attributes'],
			'the library is not a live src: a bot would load it before any verdict'
		);
		$this->assertArrayNotHasKey( 'src', $tags[0]['attributes'] );
		$this->assertSame(
			array( 'id' => 'crumbsignal-google-tags', 'type' => 'text/plain', 'data-crumbsignal-gate' => 'verdict' ),
			$tags[1]['attributes']
		);
		$this->assertStringContainsString( "gtag('config'", $tags[1]['js'], 'the config is still there, ready to be released' );
	}

	public function test_advanced_gtm_is_held_inert_when_the_gate_is_on(): void {
		$this->use_settings( array( 'advanced_consent_mode' => 1, 'gtm_id' => 'GTM-ABC123' ) );
		$this->gate( true );

		$this->call( 'google_tags' );

		$this->assertSame(
			array( 'id' => 'crumbsignal-gtm', 'type' => 'text/plain', 'data-crumbsignal-gate' => 'verdict' ),
			$this->tags()[0]['attributes']
		);
		$this->assertStringContainsString( 'googletagmanager.com/gtm.js', $this->tags()[0]['js'], 'the loader is intact' );
	}

	public function test_held_tags_have_no_consent_category_so_consent_does_not_release_them(): void {
		$this->use_settings( array( 'advanced_consent_mode' => 1, 'ga4_id' => 'G-TEST123' ) );
		$this->gate( true );

		$this->call( 'google_tags' );

		foreach ( $this->tags() as $tag ) {
			$this->assertArrayNotHasKey( 'data-crumbsignal-category', $tag['attributes'] );
		}
	}

	// ---- The page tells the script it is listening ---------------------------------------------------

	/** @dataProvider gate_values */
	public function test_the_bootstrap_config_says_whether_the_page_listens( bool $gate ): void {
		$this->use_settings( array() );
		$this->gate( $gate );

		\CrumbSignal_Frontend::consent_bootstrap();

		$this->assertStringContainsString( $gate ? '"gate":true' : '"gate":false', $this->tags()[0]['js'] );
	}

	// ---- How the gate decides ------------------------------------------------------------------------

	public function test_the_filter_can_switch_the_gate_off_even_when_guardrelay_is_on(): void {
		Functions\when( 'guardrelay_analytics_gate_active' )->justReturn( true );
		$this->use_settings( array() );

		$this->assertTrue( $this->call( 'gate_active' ), 'GuardRelay on and nothing overriding it' );

		$this->gate( false );

		$this->assertFalse( $this->call( 'gate_active' ), 'the filter wins' );
	}

	public function test_the_gate_follows_what_guardrelay_says(): void {
		Functions\when( 'guardrelay_analytics_gate_active' )->justReturn( false );
		$this->use_settings( array() );

		$this->assertFalse( $this->call( 'gate_active' ), 'GuardRelay installed but not in Exclude mode: nothing waits' );
	}
}
