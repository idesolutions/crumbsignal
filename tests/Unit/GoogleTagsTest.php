<?php
declare( strict_types=1 );

namespace CrumbSignal\Tests\Unit;

use Brain\Monkey\Functions;

require_once dirname( __DIR__, 2 ) . '/includes/class-crumbsignal-frontend.php';

/**
 * How the configured Google tags are printed: Basic mode holds them inert until consent, Advanced mode loads them
 * at once with denied defaults.
 */
final class GoogleTagsTest extends TestCase {

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

	/** @return array<int, array<string, mixed>> */
	private function tags(): array {
		return $this->printed;
	}

	// ---- Advanced and Basic Consent Mode output -------------------------------------------------------

	public function test_advanced_mode_loads_gtag_live(): void {
		$this->use_settings( array( 'advanced_consent_mode' => 1, 'ga4_id' => 'G-TEST123' ) );

		$this->call( 'google_tags' );

		$tags = $this->tags();

		$this->assertCount( 2, $tags );
		$this->assertSame( array( 'async' => true, 'src' => 'https://www.googletagmanager.com/gtag/js?id=G-TEST123' ), $tags[0]['attributes'] );
		$this->assertSame( array( 'id' => 'crumbsignal-google-tags' ), $tags[1]['attributes'] );
		$this->assertStringContainsString( "gtag('config',\"G-TEST123\")", $tags[1]['js'] );
	}

	public function test_advanced_mode_loads_gtm_live(): void {
		$this->use_settings( array( 'advanced_consent_mode' => 1, 'gtm_id' => 'GTM-ABC123' ) );

		$this->call( 'google_tags' );

		$this->assertSame( array( 'id' => 'crumbsignal-gtm' ), $this->tags()[0]['attributes'] );
	}

	public function test_strict_mode_holds_the_gtm_tag_until_consent(): void {
		$this->use_settings( array( 'advanced_consent_mode' => 0, 'gtm_id' => 'GTM-ABC123' ) );

		$this->call( 'google_tags' );

		$this->assertSame(
			array( 'id' => 'crumbsignal-gtm', 'type' => 'text/plain', 'data-crumbsignal-category' => 'google' ),
			$this->tags()[0]['attributes'],
			'strict mode holds the tag until consent'
		);
	}

	public function test_strict_mode_holds_the_gtag_tags_until_consent(): void {
		$this->use_settings( array( 'advanced_consent_mode' => 0, 'ga4_id' => 'G-TEST123' ) );

		$this->call( 'google_tags' );

		foreach ( $this->tags() as $tag ) {
			$this->assertSame( 'text/plain', $tag['attributes']['type'] );
		}
	}
}
