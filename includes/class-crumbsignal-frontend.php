<?php
/**
 * Frontend consent runtime and banner.
 *
 * @package CrumbSignal
 */

defined( 'ABSPATH' ) || exit;

final class CrumbSignal_Frontend {
	/** @var array<string, mixed> */
	private static $settings = array();

	/** @return void */
	public static function init() {
		/**
		 * Filters the settings CrumbSignal uses to render the frontend consent
		 * banner and release consent-gated scripts.
		 *
		 * This only affects what visitors see; it never touches the stored
		 * option or what the wp-admin settings screen displays, so overriding
		 * a value here (e.g. from a client-specific mu-plugin or theme) is
		 * safe from the settings form silently re-saving the override as if
		 * it were the real, admin-entered value.
		 *
		 * @param array<string, mixed> $settings Merged settings, see CrumbSignal_Settings::defaults().
		 */
		self::$settings = apply_filters( 'crumbsignal_settings', crumbsignal_get_settings() );
		if ( empty( self::$settings['enabled'] ) || is_admin() ) {
			return;
		}

		add_action( 'wp_head', array( __CLASS__, 'consent_bootstrap' ), -9999 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		// With the banner turned off nothing is printed for visitors to see: the script only releases scripts.
		if ( empty( self::$settings['no_banner'] ) ) {
			add_action( 'wp_footer', array( __CLASS__, 'banner' ), 1000 );
		}
		add_action( 'wp_body_open', array( __CLASS__, 'gtm_noscript' ), 1 );
	}

	/**
	 * Establish consent before tags have an opportunity to load.
	 *
	 * @return void
	 */
	public static function consent_bootstrap() {
		$s = self::$settings;
		$config = array(
			'version'              => (int) $s['consent_version'],
			'duration'             => (int) $s['consent_duration'],
			'advancedConsentMode'  => (bool) $s['advanced_consent_mode'],
			'adsDataRedaction'     => (bool) $s['ads_data_redaction'],
			'urlPassthrough'       => (bool) $s['url_passthrough'],
			'respectGpc'           => (bool) $s['respect_gpc'],
			'respectDnt'           => (bool) $s['respect_dnt'],
			'cookieName'           => 'crumbsignal_consent',
			'noBanner'             => ! empty( $s['no_banner'] ),
		);
		$js = <<<'JS'
(function(w,d,c){
'use strict';
w.dataLayer=w.dataLayer||[];
w.gtag=w.gtag||function(){w.dataLayer.push(arguments);};
function validCategories(x){return x&&x.necessary===true&&typeof x.preferences==='boolean'&&typeof x.analytics==='boolean'&&typeof x.marketing==='boolean';}
function read(){var m=d.cookie.match(new RegExp('(?:^|; )'+c.cookieName.replace(/([.$?*|{}()\[\]\\/+^])/g,'\\$1')+'=([^;]*)'));if(!m)return null;try{var x=JSON.parse(decodeURIComponent(m[1]));return x&&x.version===c.version&&validCategories(x.categories)?x:null;}catch(e){return null;}}
var nav=w.navigator||{}, saved=read(), rejected=(!saved&&((c.respectGpc&&nav.globalPrivacyControl===true)||(c.respectDnt&&nav.doNotTrack==='1')));
var fresh=c.noBanner?{necessary:true,preferences:true,analytics:true,marketing:true}:{necessary:true,preferences:false,analytics:false,marketing:false};
var state=saved?saved.categories:fresh;
if(rejected){state={necessary:true,preferences:false,analytics:false,marketing:false};}
w.CrumbSignalState={config:c,consent:saved,categories:state,privacySignal:rejected};
w.gtag('consent','default',{analytics_storage:state.analytics?'granted':'denied',ad_storage:state.marketing?'granted':'denied',ad_user_data:state.marketing?'granted':'denied',ad_personalization:state.marketing?'granted':'denied',functionality_storage:state.preferences?'granted':'denied',personalization_storage:state.preferences?'granted':'denied',security_storage:'granted',wait_for_update:500});
if(c.adsDataRedaction){w.gtag('set','ads_data_redaction',true);}
if(c.urlPassthrough){w.gtag('set','url_passthrough',true);}
w.dataLayer.push({event:'crumbsignal_ready',consent_analytics:!!state.analytics,consent_marketing:!!state.marketing,consent_preferences:!!state.preferences,consent_source:saved?'stored':(rejected?'privacy_signal':(c.noBanner?'no_banner':'default'))});
})(window,document,
JS;
		$js .= wp_json_encode( $config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ');';

		wp_print_inline_script_tag( $js, array( 'id' => 'crumbsignal-consent-bootstrap' ) );
		self::google_tags();
	}

	/** @return void */
	private static function google_tags() {
		$s       = self::$settings;
		$tag_ids = array_values( array_filter( array( $s['ga4_id'], $s['google_ads_id'] ) ) );
		if ( ! empty( $s['gtm_id'] ) ) {
			$attributes = array( 'id' => 'crumbsignal-gtm' );
			if ( empty( $s['advanced_consent_mode'] ) ) {
				$attributes['type']                      = 'text/plain';
				$attributes['data-crumbsignal-category'] = 'google';
			}
			$loader = "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer'," . wp_json_encode( $s['gtm_id'] ) . ');';
			wp_print_inline_script_tag( $loader, $attributes );
		}

		if ( empty( $s['gtm_id'] ) && ! empty( $tag_ids ) ) {
			$library_url = 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $tag_ids[0] );
			if ( ! empty( $s['advanced_consent_mode'] ) ) {
				wp_print_script_tag( array( 'async' => true, 'src' => $library_url ) );
				$configuration = "gtag('js',new Date());";
				foreach ( $tag_ids as $tag_id ) {
					$configuration .= "gtag('config'," . wp_json_encode( $tag_id ) . ');';
				}
				wp_print_inline_script_tag( $configuration, array( 'id' => 'crumbsignal-google-tags' ) );
			} else {
				wp_print_script_tag( array( 'type' => 'text/plain', 'data-crumbsignal-category' => 'google', 'data-src' => $library_url ) );
				wp_print_inline_script_tag( "gtag('js',new Date());", array( 'type' => 'text/plain', 'data-crumbsignal-category' => 'google' ) );
				if ( ! empty( $s['ga4_id'] ) ) {
					wp_print_inline_script_tag( "gtag('config'," . wp_json_encode( $s['ga4_id'] ) . ');', array( 'type' => 'text/plain', 'data-crumbsignal-category' => 'analytics' ) );
				}
				if ( ! empty( $s['google_ads_id'] ) ) {
					wp_print_inline_script_tag( "gtag('config'," . wp_json_encode( $s['google_ads_id'] ) . ');', array( 'type' => 'text/plain', 'data-crumbsignal-category' => 'marketing' ) );
				}
			}
		}
	}

	/** @return void */
	public static function assets() {
		$cleanup_rules = array(
			'preferences' => array(),
			'analytics'   => array( '_ga', '_ga_*', '_gid', '_gat', '_gat_*' ),
			'marketing'   => array( '_gcl_au', '_gcl_aw_*', '_gcl_dc_*', '_fbp' ),
		);
		foreach ( array( 'preferences', 'analytics', 'marketing' ) as $category ) {
			$names = trim( (string) ( self::$settings[ 'cookies_' . $category ] ?? '' ) );
			if ( '' !== $names ) {
				$cleanup_rules[ $category ] = array_merge( $cleanup_rules[ $category ], array_map( 'trim', explode( ',', $names ) ) );
			}
		}
		foreach ( $cleanup_rules as $category => $rules ) {
			$cleanup_rules[ $category ] = array_values( array_unique( array_filter( $rules ) ) );
		}

		$show_banner = empty( self::$settings['no_banner'] );

		if ( $show_banner ) {
			wp_enqueue_style( 'crumbsignal', CRUMBSIGNAL_URL . 'assets/css/crumbsignal.css', array(), CRUMBSIGNAL_VERSION );
		}

		wp_enqueue_script( 'crumbsignal', CRUMBSIGNAL_URL . 'assets/js/crumbsignal.js', array(), CRUMBSIGNAL_VERSION, true );
		wp_add_inline_script(
			'crumbsignal',
			'window.CrumbSignalSettings=' . wp_json_encode(
				array(
					'policyUrl'       => self::$settings['policy_url'],
					'showRevisit'     => (bool) self::$settings['show_revisit'],
					'advancedConsent' => (bool) self::$settings['advanced_consent_mode'],
					'cleanupRules'    => $cleanup_rules,
				)
			) . ';',
			'before'
		);

		if ( ! $show_banner ) {
			return;
		}

		$custom = sprintf(
			'#crumbsignal-root{--crumbsignal-accent:%1$s;--crumbsignal-bg:%2$s;--crumbsignal-text:%3$s;--crumbsignal-accent-text:%4$s;}',
			sanitize_hex_color( self::$settings['accent_color'] ) ?: '#6d4aff',
			sanitize_hex_color( self::$settings['background_color'] ) ?: '#ffffff',
			sanitize_hex_color( self::$settings['text_color'] ) ?: '#17151f',
			sanitize_hex_color( self::$settings['button_text_color'] ?? '' ) ?: '#ffffff'
		);
		wp_add_inline_style( 'crumbsignal', $custom );
	}

	/** @return void */
	public static function gtm_noscript() {
		if ( empty( self::$settings['gtm_id'] ) || empty( self::$settings['advanced_consent_mode'] ) ) {
			return;
		}
		?><noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr( self::$settings['gtm_id'] ); ?>" height="0" width="0" style="display:none;visibility:hidden" title="<?php esc_attr_e( 'Google Tag Manager', 'crumbsignal' ); ?>"></iframe></noscript><?php
	}

	/**
	 * `[crumbsignal_preferences label="Cookie Preferences"]`: a link that reopens the preferences popup, for a
	 * footer or menu when the floating button is switched off. Prints nothing if there is no banner to open.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function preferences_link( $atts ) {
		if ( empty( self::$settings['enabled'] ) || ! empty( self::$settings['no_banner'] ) ) {
			return '';
		}

		$atts = shortcode_atts( array( 'label' => __( 'Cookie Preferences', 'crumbsignal' ) ), $atts, 'crumbsignal_preferences' );

		return '<a href="#crumbsignal-preferences" class="crumbsignal-open-preferences">' . esc_html( $atts['label'] ) . '</a>';
	}

	/** @return void */
	public static function banner() {
		$s             = self::$settings;
		$revisit_label = __( 'Open cookie preferences', 'crumbsignal' );
		?>
		<div id="crumbsignal-root" class="crumbsignal crumbsignal--<?php echo esc_attr( $s['position'] ); ?><?php echo 'right' === $s['revisit_position'] ? ' crumbsignal--revisit-right' : ''; ?>" hidden>
			<section class="crumbsignal__banner" role="dialog" aria-modal="false" aria-live="polite" aria-labelledby="crumbsignal-heading" aria-describedby="crumbsignal-description">
				<div class="crumbsignal__copy"><h2 id="crumbsignal-heading"><?php echo esc_html( $s['heading'] ); ?></h2><p id="crumbsignal-description"><?php echo esc_html( $s['description'] ); ?> <?php if ( $s['policy_url'] ) : ?><a href="<?php echo esc_url( $s['policy_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Learn more', 'crumbsignal' ); ?></a><?php endif; ?></p></div>
				<div class="crumbsignal__actions"><button type="button" class="crumbsignal__button crumbsignal__button--secondary" data-crumbsignal-action="customize"><?php echo esc_html( $s['customize_label'] ); ?></button><button type="button" class="crumbsignal__button crumbsignal__button--reject" data-crumbsignal-action="reject"><?php echo esc_html( $s['reject_label'] ); ?></button><button type="button" class="crumbsignal__button crumbsignal__button--primary" data-crumbsignal-action="accept"><?php echo esc_html( $s['accept_label'] ); ?></button></div>
			</section>
			<div class="crumbsignal__overlay" hidden></div>
			<section class="crumbsignal__modal" role="dialog" aria-modal="true" aria-labelledby="crumbsignal-modal-heading" hidden>
				<header><div><span><?php esc_html_e( 'PRIVACY CENTER', 'crumbsignal' ); ?></span><h2 id="crumbsignal-modal-heading"><?php echo esc_html( $s['preferences_heading'] ); ?></h2></div><button type="button" class="crumbsignal__close" data-crumbsignal-action="close" aria-label="<?php esc_attr_e( 'Close cookie preferences', 'crumbsignal' ); ?>"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg></button></header>
				<div class="crumbsignal__categories">
					<?php self::category( 'necessary', __( 'Necessary', 'crumbsignal' ), $s['necessary_description'], true ); ?>
					<?php self::category( 'preferences', __( 'Preferences', 'crumbsignal' ), $s['preferences_description'] ); ?>
					<?php self::category( 'analytics', __( 'Analytics', 'crumbsignal' ), $s['analytics_description'] ); ?>
					<?php self::category( 'marketing', __( 'Marketing', 'crumbsignal' ), $s['marketing_description'] ); ?>
				</div>
				<footer><button type="button" class="crumbsignal__button crumbsignal__button--reject" data-crumbsignal-action="reject"><?php echo esc_html( $s['reject_label'] ); ?></button><button type="button" class="crumbsignal__button crumbsignal__button--primary" data-crumbsignal-action="save"><?php echo esc_html( $s['save_label'] ); ?></button></footer>
			</section>
			<?php if ( $s['show_revisit'] ) : ?><button type="button" class="crumbsignal__revisit" data-crumbsignal-action="customize" aria-label="<?php echo esc_attr( $revisit_label ); ?>" title="<?php echo esc_attr( $revisit_label ); ?>" hidden><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 10 10 2 2 0 0 1-2-2 2 2 0 0 1-2-2 2 2 0 0 1-2-2 2 2 0 0 1-2-2 2 2 0 0 1-2-2Z"/><circle cx="8" cy="11" r="1.3"/><circle cx="12" cy="16" r="1.3"/><circle cx="7" cy="16" r="1.3"/></svg></button><?php endif; ?>
		</div>
		<?php
	}

	/** @param string $key Key. @param string $title Title. @param string $description Description. @param bool $required Required. @return void */
	private static function category( $key, $title, $description, $required = false ) {
		// translators: %s is the cookie category name (e.g. "Analytics").
		$switch_label = sprintf( __( 'Allow %s cookies', 'crumbsignal' ), $title );
		?>
		<div class="crumbsignal__category"><div><h3><?php echo esc_html( $title ); ?></h3><p><?php echo esc_html( $description ); ?></p></div><?php if ( $required ) : ?><span class="crumbsignal__always"><?php esc_html_e( 'Always Active', 'crumbsignal' ); ?></span><?php else : ?><label class="crumbsignal__switch"><span class="screen-reader-text"><?php echo esc_html( $switch_label ); ?></span><input type="checkbox" data-crumbsignal-category-input="<?php echo esc_attr( $key ); ?>"><span aria-hidden="true"></span></label><?php endif; ?></div>
		<?php
	}
}
