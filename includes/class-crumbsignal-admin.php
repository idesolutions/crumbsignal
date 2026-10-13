<?php
/**
 * WordPress administration screen.
 *
 * @package CrumbSignal
 */

defined( 'ABSPATH' ) || exit;

final class CrumbSignal_Admin {
	/** @return void */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CRUMBSIGNAL_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/** @return void */
	public static function menu() {
		add_options_page(
			__( 'CrumbSignal', 'crumbsignal' ),
			__( 'CrumbSignal', 'crumbsignal' ),
			'manage_options',
			'crumbsignal',
			array( __CLASS__, 'render' )
		);
	}

	/** @param string $hook Current hook. @return void */
	public static function assets( $hook ) {
		if ( 'settings_page_crumbsignal' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'crumbsignal', CRUMBSIGNAL_URL . 'assets/css/crumbsignal.css', array(), CRUMBSIGNAL_VERSION );
		wp_enqueue_style( 'crumbsignal-admin', CRUMBSIGNAL_URL . 'assets/css/admin.css', array( 'crumbsignal' ), CRUMBSIGNAL_VERSION );
		wp_enqueue_script( 'crumbsignal-admin', CRUMBSIGNAL_URL . 'assets/js/admin.js', array(), CRUMBSIGNAL_VERSION, true );
		wp_add_inline_script(
			'crumbsignal-admin',
			'window.CrumbSignalAdminL10n=' . wp_json_encode(
				array(
					// translators: %1$s is a contrast description (e.g. "Accept button text contrast"), %2$s is a ratio like "4.87".
					'contrastLine'  => __( '%1$s: %2$s:1 (WCAG AA needs 4.5:1)', 'crumbsignal' ),
					'contrastGood'  => __( 'Good', 'crumbsignal' ),
					'contrastLow'   => __( 'Low', 'crumbsignal' ),
					'acceptLabel'   => __( 'Accept button text contrast', 'crumbsignal' ),
					'rejectLabel'   => __( 'Body text & Reject button contrast', 'crumbsignal' ),
				)
			) . ';',
			'before'
		);
	}

	/** @param string[] $links Links. @return string[] */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=crumbsignal' ) ) . '">' . esc_html__( 'Settings', 'crumbsignal' ) . '</a>' );
		return $links;
	}

	/** @return void */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = crumbsignal_get_settings();
		?>
		<div class="wrap crumbsignal-admin">
			<?php settings_errors( 'crumbsignal_settings' ); ?>
			<header class="crumbsignal-admin__header">
				<div><span class="crumbsignal-admin__eyebrow"><?php esc_html_e( 'COOKIE CONSENT, WITHOUT THE CLUTTER', 'crumbsignal' ); ?></span><h1><?php esc_html_e( 'CrumbSignal', 'crumbsignal' ); ?></h1><p><?php esc_html_e( 'Clear consent. Clean signals.', 'crumbsignal' ); ?></p></div>
			</header>

			<?php self::preview( $s ); ?>

			<form id="crumbsignal-form" method="post" action="options.php">
				<?php settings_fields( 'crumbsignal_settings_group' ); ?>
				<nav class="crumbsignal-tabs" role="tablist" aria-label="<?php esc_attr_e( 'CrumbSignal settings', 'crumbsignal' ); ?>">
					<?php
					self::tab( 'general', __( 'General', 'crumbsignal' ), true );
					self::tab( 'content', __( 'Content', 'crumbsignal' ) );
					self::tab( 'google', __( 'Google & GTM', 'crumbsignal' ) );
					self::tab( 'scripts', __( 'Script Manager', 'crumbsignal' ) );
					self::tab( 'design', __( 'Design', 'crumbsignal' ) );
					?>
				</nav>

				<section class="crumbsignal-panel is-active" id="crumbsignal-panel-general" data-panel="general" role="tabpanel" aria-labelledby="crumbsignal-tab-general">
					<h2><?php esc_html_e( 'Consent behavior', 'crumbsignal' ); ?></h2>
					<?php self::toggle( 'enabled', __( 'Enable the cookie banner', 'crumbsignal' ), $s['enabled'] ); ?>
					<?php self::toggle( 'no_banner', __( 'Turn the banner off but keep my scripts running', 'crumbsignal' ), $s['no_banner'] ); ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'With this on, CrumbSignal shows no banner and runs your analytics, marketing and preferences scripts for every visitor without asking. It still respects a choice a visitor already made, and Global Privacy Control or Do Not Track if those options below are on. Use it only where consent is not required for your visitors; you remain responsible for complying with the law that applies to you. A common use is keeping bots out of analytics with GuardRelay without showing anyone a banner. (To remove CrumbSignal entirely, including its script handling, turn off "Enable the cookie banner" above instead.)', 'crumbsignal' ); ?></p></div>
					<div class="crumbsignal-grid">
						<?php self::number( 'consent_duration', __( 'Consent duration (days)', 'crumbsignal' ), $s['consent_duration'], 1, 730 ); ?>
						<?php self::number( 'consent_version', __( 'Consent version', 'crumbsignal' ), $s['consent_version'], 1, 9999, __( 'Increase this to ask every visitor for consent again.', 'crumbsignal' ) ); ?>
						<?php self::select( 'position', __( 'Banner position', 'crumbsignal' ), $s['position'], array( 'bottom' => __( 'Bottom bar', 'crumbsignal' ), 'bottom-left' => __( 'Bottom left', 'crumbsignal' ), 'bottom-right' => __( 'Bottom right', 'crumbsignal' ) ) ); ?>
						<?php self::url( 'policy_url', __( 'Cookie or privacy policy URL', 'crumbsignal' ), $s['policy_url'] ); ?>
					</div>
					<?php self::toggle( 'show_revisit', __( 'Show the floating privacy choices button', 'crumbsignal' ), $s['show_revisit'] ); ?>
					<div class="crumbsignal-grid">
						<?php self::select( 'revisit_position', __( 'Privacy choices button side', 'crumbsignal' ), $s['revisit_position'], array( 'left' => __( 'Bottom left', 'crumbsignal' ), 'right' => __( 'Bottom right', 'crumbsignal' ) ) ); ?>
					</div>
					<p class="description crumbsignal-help"><?php echo wp_kses( __( 'Prefer your own link instead? Turn the button off, then add <code>[crumbsignal_preferences]</code> to a page or widget, or point any menu item or button at <code>#crumbsignal-preferences</code> (or give it the class <code>crumbsignal-open-preferences</code>). Clicking it reopens the preferences popup.', 'crumbsignal' ), array( 'code' => array() ) ); ?></p>
					<?php self::toggle( 'respect_gpc', __( 'Honor Global Privacy Control as a rejection of optional cookies', 'crumbsignal' ), $s['respect_gpc'] ); ?>
					<?php self::toggle( 'respect_dnt', __( 'Honor legacy Do Not Track as a rejection of optional cookies', 'crumbsignal' ), $s['respect_dnt'] ); ?>
				</section>

				<section class="crumbsignal-panel" id="crumbsignal-panel-content" data-panel="content" role="tabpanel" aria-labelledby="crumbsignal-tab-content">
					<h2><?php esc_html_e( 'Banner content', 'crumbsignal' ); ?></h2>
					<?php self::text( 'heading', __( 'Banner heading', 'crumbsignal' ), $s['heading'] ); ?>
					<?php self::textarea( 'description', __( 'Banner description', 'crumbsignal' ), $s['description'] ); ?>
					<div class="crumbsignal-grid">
						<?php self::text( 'accept_label', __( 'Accept button', 'crumbsignal' ), $s['accept_label'] ); ?>
						<?php self::text( 'reject_label', __( 'Reject button', 'crumbsignal' ), $s['reject_label'] ); ?>
						<?php self::text( 'customize_label', __( 'Customize button', 'crumbsignal' ), $s['customize_label'] ); ?>
						<?php self::text( 'save_label', __( 'Save button', 'crumbsignal' ), $s['save_label'] ); ?>
					</div>
					<h3><?php esc_html_e( 'Category descriptions', 'crumbsignal' ); ?></h3>
					<?php self::textarea( 'necessary_description', __( 'Necessary', 'crumbsignal' ), $s['necessary_description'] ); ?>
					<?php self::textarea( 'preferences_description', __( 'Preferences', 'crumbsignal' ), $s['preferences_description'] ); ?>
					<?php self::textarea( 'analytics_description', __( 'Analytics', 'crumbsignal' ), $s['analytics_description'] ); ?>
					<?php self::textarea( 'marketing_description', __( 'Marketing', 'crumbsignal' ), $s['marketing_description'] ); ?>
				</section>

				<section class="crumbsignal-panel" id="crumbsignal-panel-google" data-panel="google" role="tabpanel" aria-labelledby="crumbsignal-tab-google">
					<h2><?php esc_html_e( 'Google tags and Consent Mode v2', 'crumbsignal' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Leave IDs blank if another tool installs that tag. CrumbSignal still establishes consent defaults and sends consent updates.', 'crumbsignal' ); ?></p>
					<div class="crumbsignal-grid">
						<?php self::text( 'gtm_id', __( 'Google Tag Manager ID', 'crumbsignal' ), $s['gtm_id'], 'GTM-XXXXXXX' ); ?>
						<?php self::text( 'ga4_id', __( 'GA4 Measurement ID', 'crumbsignal' ), $s['ga4_id'], 'G-XXXXXXXXXX' ); ?>
						<?php self::text( 'google_ads_id', __( 'Google Ads ID', 'crumbsignal' ), $s['google_ads_id'], 'AW-XXXXXXXXX' ); ?>
					</div>
					<?php self::toggle( 'advanced_consent_mode', __( 'Advanced Consent Mode: allow Google tags to load with denied defaults', 'crumbsignal' ), $s['advanced_consent_mode'] ); ?>
					<?php self::toggle( 'ads_data_redaction', __( 'Enable ads data redaction when ad storage is denied', 'crumbsignal' ), $s['ads_data_redaction'] ); ?>
					<?php self::toggle( 'url_passthrough', __( 'Enable URL passthrough for eligible Google tags', 'crumbsignal' ), $s['url_passthrough'] ); ?>
					<div class="notice notice-info inline"><p><?php esc_html_e( 'CrumbSignal pushes crumbsignal_ready and crumbsignal_consent_update events to dataLayer. The event includes consent_analytics, consent_marketing, and consent_preferences booleans.', 'crumbsignal' ); ?></p></div>
				</section>

				<section class="crumbsignal-panel" id="crumbsignal-panel-scripts" data-panel="scripts" role="tabpanel" aria-labelledby="crumbsignal-tab-scripts">
					<h2><?php esc_html_e( 'Script Manager', 'crumbsignal' ); ?></h2>
					<p><?php esc_html_e( 'Add third-party scripts here so they remain inert until the visitor accepts their category.', 'crumbsignal' ); ?></p>
					<div id="crumbsignal-script-list">
						<?php foreach ( $s['scripts'] as $i => $script ) : self::script_row( $i, $script ); endforeach; ?>
					</div>
					<button type="button" class="button" id="crumbsignal-add-script"><?php esc_html_e( 'Add script', 'crumbsignal' ); ?></button>
						<template id="crumbsignal-script-template"><?php self::script_row( '__INDEX__', array( 'name' => '', 'category' => 'analytics', 'location' => 'head', 'source' => '', 'inline' => '', 'cookies' => '', 'enabled' => 1 ) ); ?></template>
					<div class="crumbsignal-code-help"><h3><?php esc_html_e( 'Gate scripts added elsewhere', 'crumbsignal' ); ?></h3><code>&lt;script type="text/plain" data-crumbsignal-category="analytics" data-src="https://example.com/script.js"&gt;&lt;/script&gt;</code></div>
				</section>

				<section class="crumbsignal-panel" id="crumbsignal-panel-design" data-panel="design" role="tabpanel" aria-labelledby="crumbsignal-tab-design">
					<h2><?php esc_html_e( 'Appearance', 'crumbsignal' ); ?></h2>
					<div class="crumbsignal-grid crumbsignal-colors">
						<?php self::color( 'accent_color', __( 'Accent color', 'crumbsignal' ), $s['accent_color'], __( 'Used for the Accept button.', 'crumbsignal' ) ); ?>
						<?php self::color( 'background_color', __( 'Background color', 'crumbsignal' ), $s['background_color'], __( 'Banner background. Also the Reject button\'s text color.', 'crumbsignal' ) ); ?>
						<?php self::color( 'text_color', __( 'Text color', 'crumbsignal' ), $s['text_color'], __( 'Banner text. Also the Reject button\'s background.', 'crumbsignal' ) ); ?>
						<?php self::color( 'button_text_color', __( 'Accept button text color', 'crumbsignal' ), $s['button_text_color'], __( 'Text on the Accept button. Choose a dark color if your accent color is light.', 'crumbsignal' ) ); ?>
					</div>
					<div class="crumbsignal-contrast" id="crumbsignal-contrast-report" role="status">
						<p data-contrast="accept"></p>
						<p data-contrast="reject"></p>
					</div>
					<?php self::textarea( 'custom_css', __( 'Custom CSS', 'crumbsignal' ), $s['custom_css'], 10 ); ?>
				</section>

				<?php submit_button( __( 'Save CrumbSignal Settings', 'crumbsignal' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a live, decorative preview of the banner that reflects the
	 * current field values and updates as the admin edits them.
	 *
	 * Marked `aria-hidden` because it is a purely visual aid: every value
	 * shown here already has an editable, labeled field elsewhere on the
	 * page, so a screen reader would otherwise announce a confusing set of
	 * duplicate, non-functional buttons.
	 *
	 * @param array<string, mixed> $s Current settings.
	 * @return void
	 */
	private static function preview( $s ) { ?>
		<section class="crumbsignal-preview" aria-hidden="true">
			<div class="crumbsignal-preview__label"><?php esc_html_e( 'Live preview', 'crumbsignal' ); ?></div>
			<div class="crumbsignal-preview__stage">
				<div id="crumbsignal-preview-widget" class="crumbsignal crumbsignal--<?php echo esc_attr( $s['position'] ); ?>" style="--crumbsignal-accent:<?php echo esc_attr( $s['accent_color'] ); ?>;--crumbsignal-bg:<?php echo esc_attr( $s['background_color'] ); ?>;--crumbsignal-text:<?php echo esc_attr( $s['text_color'] ); ?>;--crumbsignal-accent-text:<?php echo esc_attr( $s['button_text_color'] ); ?>">
					<div class="crumbsignal__banner">
						<div class="crumbsignal__copy">
							<h2 id="crumbsignal-preview-heading"><?php echo esc_html( $s['heading'] ); ?></h2>
							<p id="crumbsignal-preview-description"><?php echo esc_html( $s['description'] ); ?></p>
						</div>
						<div class="crumbsignal__actions">
							<button type="button" tabindex="-1" class="crumbsignal__button crumbsignal__button--secondary" id="crumbsignal-preview-customize"><?php echo esc_html( $s['customize_label'] ); ?></button>
							<button type="button" tabindex="-1" class="crumbsignal__button crumbsignal__button--reject" id="crumbsignal-preview-reject"><?php echo esc_html( $s['reject_label'] ); ?></button>
							<button type="button" tabindex="-1" class="crumbsignal__button crumbsignal__button--primary" id="crumbsignal-preview-accept"><?php echo esc_html( $s['accept_label'] ); ?></button>
						</div>
					</div>
				</div>
			</div>
		</section>
		<?php
	}

	/** @param string $tab Tab slug. @param string $label Tab label. @param bool $active Whether the tab is selected on load. @return void */
	private static function tab( $tab, $label, $active = false ) { ?>
		<button type="button" id="crumbsignal-tab-<?php echo esc_attr( $tab ); ?>" class="<?php echo $active ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo $active ? 'true' : 'false'; ?>" aria-controls="crumbsignal-panel-<?php echo esc_attr( $tab ); ?>" tabindex="<?php echo $active ? '0' : '-1'; ?>" data-tab="<?php echo esc_attr( $tab ); ?>"><?php echo esc_html( $label ); ?></button>
		<?php
	}

	/**
	 * Build a settings field's `name` attribute value.
	 *
	 * Escapes internally and is safe to echo directly, but call sites still
	 * wrap it in esc_attr() since this is a plain helper method, not one of
	 * WordPress's own recognized escaping functions.
	 *
	 * @param string $key Settings key.
	 * @return string
	 */
	private static function field_name( $key ) { return 'crumbsignal_settings[' . esc_attr( $key ) . ']'; }
	private static function text( $key, $label, $value, $placeholder = '' ) { ?>
		<label class="crumbsignal-field"><span><?php echo esc_html( $label ); ?></span><input type="text" name="<?php echo esc_attr( self::field_name( $key ) ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"></label><?php
	}
	private static function url( $key, $label, $value ) { ?>
		<label class="crumbsignal-field"><span><?php echo esc_html( $label ); ?></span><input type="url" name="<?php echo esc_attr( self::field_name( $key ) ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="https://"></label><?php
	}
	private static function number( $key, $label, $value, $min, $max, $help = '' ) { ?>
		<label class="crumbsignal-field"><span><?php echo esc_html( $label ); ?></span><input type="number" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" name="<?php echo esc_attr( self::field_name( $key ) ); ?>" value="<?php echo esc_attr( $value ); ?>"><?php if ( $help ) : ?><small><?php echo esc_html( $help ); ?></small><?php endif; ?></label><?php
	}
	private static function select( $key, $label, $value, $options ) { ?>
		<label class="crumbsignal-field"><span><?php echo esc_html( $label ); ?></span><select name="<?php echo esc_attr( self::field_name( $key ) ); ?>"><?php foreach ( $options as $option => $title ) : ?><option value="<?php echo esc_attr( $option ); ?>" <?php selected( $value, $option ); ?>><?php echo esc_html( $title ); ?></option><?php endforeach; ?></select></label><?php
	}
	private static function textarea( $key, $label, $value, $rows = 4 ) { ?>
		<label class="crumbsignal-field crumbsignal-field--wide"><span><?php echo esc_html( $label ); ?></span><textarea rows="<?php echo esc_attr( $rows ); ?>" name="<?php echo esc_attr( self::field_name( $key ) ); ?>"><?php echo esc_textarea( $value ); ?></textarea></label><?php
	}
	private static function color( $key, $label, $value, $help = '' ) { ?>
		<label class="crumbsignal-field"><span><?php echo esc_html( $label ); ?></span><input type="color" name="<?php echo esc_attr( self::field_name( $key ) ); ?>" value="<?php echo esc_attr( $value ); ?>"><?php if ( $help ) : ?><small><?php echo esc_html( $help ); ?></small><?php endif; ?></label><?php
	}
	private static function toggle( $key, $label, $checked ) { ?>
		<label class="crumbsignal-toggle"><input type="checkbox" name="<?php echo esc_attr( self::field_name( $key ) ); ?>" value="1" <?php checked( $checked ); ?>><span aria-hidden="true"></span><b><?php echo esc_html( $label ); ?></b></label><?php
	}
	private static function script_row( $index, $script ) { ?>
		<div class="crumbsignal-script-row">
			<div class="crumbsignal-script-row__head"><strong><?php esc_html_e( 'Controlled script', 'crumbsignal' ); ?></strong><button type="button" class="button-link-delete crumbsignal-remove-script"><?php esc_html_e( 'Remove', 'crumbsignal' ); ?></button></div>
			<div class="crumbsignal-grid">
				<label class="crumbsignal-field"><span><?php esc_html_e( 'Name', 'crumbsignal' ); ?></span><input type="text" name="crumbsignal_settings[scripts][<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $script['name'] ); ?>"></label>
				<label class="crumbsignal-field"><span><?php esc_html_e( 'Category', 'crumbsignal' ); ?></span><select name="crumbsignal_settings[scripts][<?php echo esc_attr( $index ); ?>][category]"><option value="preferences" <?php selected( $script['category'], 'preferences' ); ?>><?php esc_html_e( 'Preferences', 'crumbsignal' ); ?></option><option value="analytics" <?php selected( $script['category'], 'analytics' ); ?>><?php esc_html_e( 'Analytics', 'crumbsignal' ); ?></option><option value="marketing" <?php selected( $script['category'], 'marketing' ); ?>><?php esc_html_e( 'Marketing', 'crumbsignal' ); ?></option></select></label>
				<label class="crumbsignal-field"><span><?php esc_html_e( 'Location', 'crumbsignal' ); ?></span><select name="crumbsignal_settings[scripts][<?php echo esc_attr( $index ); ?>][location]"><option value="head" <?php selected( $script['location'], 'head' ); ?>><?php esc_html_e( 'Head', 'crumbsignal' ); ?></option><option value="body" <?php selected( $script['location'], 'body' ); ?>><?php esc_html_e( 'Footer', 'crumbsignal' ); ?></option></select></label>
				<label class="crumbsignal-toggle crumbsignal-toggle--compact"><input type="checkbox" name="crumbsignal_settings[scripts][<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( $script['enabled'] ); ?>><span aria-hidden="true"></span><b><?php esc_html_e( 'Enabled', 'crumbsignal' ); ?></b></label>
			</div>
			<label class="crumbsignal-field crumbsignal-field--wide"><span><?php esc_html_e( 'External script URL', 'crumbsignal' ); ?></span><input type="url" name="crumbsignal_settings[scripts][<?php echo esc_attr( $index ); ?>][source]" value="<?php echo esc_attr( $script['source'] ); ?>" placeholder="https://"></label>
			<label class="crumbsignal-field crumbsignal-field--wide"><span><?php esc_html_e( 'Inline JavaScript (without script tags)', 'crumbsignal' ); ?></span><textarea rows="4" name="crumbsignal_settings[scripts][<?php echo esc_attr( $index ); ?>][inline]"><?php echo esc_textarea( $script['inline'] ); ?></textarea></label>
			<label class="crumbsignal-field crumbsignal-field--wide"><span><?php esc_html_e( 'First-party cookies to remove when consent is withdrawn', 'crumbsignal' ); ?></span><input type="text" name="crumbsignal_settings[scripts][<?php echo esc_attr( $index ); ?>][cookies]" value="<?php echo esc_attr( $script['cookies'] ?? '' ); ?>" placeholder="cookie_name, service_prefix_*"><small><?php esc_html_e( 'Comma-separated. Use a trailing * to match a prefix. Third-party and HttpOnly cookies cannot be removed by browser JavaScript.', 'crumbsignal' ); ?></small></label>
		</div><?php
	}
}
