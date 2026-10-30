=== CrumbSignal ===
Contributors: idesolutions
Tags: cookie consent, consent mode, google tag manager, analytics, privacy
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Clear consent. Clean signals. A customizable cookie banner that holds optional scripts until consent and connects to Google Consent Mode v2.

== Description ==
CrumbSignal is a self-hosted WordPress consent manager. It stores consent in a first-party cookie, keeps configured scripts inactive until the matching category is accepted, and communicates visitor choices to Google tags.

= Features =

* Accept all, reject optional, and granular preferences
* Necessary, preferences, analytics, and marketing categories
* Google Consent Mode v2 defaults and updates
* Google Tag Manager, GA4, and Google Ads IDs
* Basic and advanced Google consent configurations
* dataLayer events for custom GTM triggers
* Category-based script gating with a simple markup attribute (no code is entered or stored in the plugin)
* First-party cookie cleanup rules when consent is withdrawn
* Global Privacy Control support
* Optional "banner off" mode: show no banner but keep your scripts running (for sites where consent isn't required)
* Accessible modal, focus management, and reduced-motion support
* Custom colors, copy, and position
* No account, external dashboard, or pageview limits

CrumbSignal is a technical consent tool. Correct configuration and accurate privacy disclosures remain the website owner's responsibility. This plugin does not provide legal advice or guarantee compliance with any law.

= External services =

CrumbSignal itself sends no data anywhere and has no account, dashboard or remote API. The only third-party resources it can load are Google's, and only if the site owner enters a Google Tag Manager, GA4 or Google Ads ID in Settings > CrumbSignal > Google & GTM.

**Google Tag Manager and the Google tag (gtag.js)**

* What it is: Google's tag-loading scripts, `https://www.googletagmanager.com/gtm.js` (Tag Manager) and `https://www.googletagmanager.com/gtag/js` (GA4 and Google Ads), plus the `https://www.googletagmanager.com/ns.html` noscript fallback when Advanced Consent Mode is on.
* When it loads: in Basic Consent Mode, only after the visitor accepts Analytics or Marketing. In Advanced Consent Mode, on page load, with all consent signals set to denied until the visitor chooses.
* What is sent: the visitor's browser requests the script from Google, so Google receives the visitor's IP address and browser details. CrumbSignal also tells the Google tag the visitor's consent choices (Google Consent Mode). Whatever the tags in your own Tag Manager container or GA4 / Ads property then collect is governed by your configuration and Google's terms.
* Google Terms of Service: https://policies.google.com/terms
* Google Privacy Policy: https://policies.google.com/privacy

Any other third-party script you mark for gating yourself loads from the address you give it, and only after the visitor accepts its category. You are responsible for disclosing those services to your visitors.

== Installation ==
1. Upload the `crumbsignal` folder to `/wp-content/plugins/` or install the ZIP from Plugins > Add New > Upload Plugin.
2. Activate CrumbSignal.
3. Open Settings > CrumbSignal.
4. Configure banner copy and Google tags, and mark any other scripts that require consent with a category attribute.
5. Test Accept, Reject, and Customize in a private browser window before publishing.

== Frequently Asked Questions ==
= Does CrumbSignal scan my website for cookies? =
No. Version 1 focuses on transparent, deterministic control of configured scripts. Automated scanners can miss scripts or classify them incorrectly.

= Does Google Tag Manager load before consent? =
In Advanced Consent Mode, the container may load with denied consent defaults. In Basic Consent Mode, CrumbSignal only loads the configured GTM container after analytics or marketing consent. Consent defaults are established before either mode loads Google tags.

= What dataLayer events are available? =
`crumbsignal_ready` fires after the default or stored consent state is established. `crumbsignal_consent_update` fires when the visitor saves a choice. Both include consent booleans for analytics, marketing, and preferences.

= Can I control scripts added outside CrumbSignal? =
Yes. CrumbSignal does not store or print scripts for you; it gates the ones you add yourself. Change the script type to `text/plain`, add `data-crumbsignal-category="analytics"` (or preferences/marketing), and move an external URL from `src` to `data-src`. The Script Gating tab also lets you list the cookie names those scripts set, so they are cleared when a visitor withdraws consent.

= Can I use a footer link instead of the floating button? =
Yes. Turn off "Show the floating privacy choices button" in Settings > CrumbSignal > General, then add the `[crumbsignal_preferences]` shortcode (optionally `label="Your Privacy Choices"`) to a footer widget or page. You can also point any menu item at a URL ending in `#crumbsignal-preferences` (for example `#crumbsignal-preferences` or `https://example.com/#crumbsignal-preferences`), or give any link or button the class `crumbsignal-open-preferences`. Clicking it reopens the preferences popup.

= Will page caching affect the cookie banner or consent signals? =
CrumbSignal reads and writes consent entirely in the visitor's browser: a first-party cookie plus a small bootstrap script that runs before any other script, both served as part of the normal (cacheable) page HTML. A correctly configured cache does not need to exclude pages using CrumbSignal. The one thing to check is whether your host or a JS-optimization plugin rewrites, defers, combines, or strips inline `<script>` tags — if so, exclude the `crumbsignal-consent-bootstrap` script (and the consent-gated `data-crumbsignal-category` scripts) from that optimization, since they must run unmodified and in order.

== Screenshots ==

1. The visitor-facing consent banner with Accept, Reject, and Customize actions.
2. The granular cookie preferences panel, opened from Customize or the revisit button.
3. The CrumbSignal settings screen: General tab (consent duration, banner position, GPC/DNT).
4. The Google & GTM tab: GTM/GA4/Ads IDs and Basic vs. Advanced Consent Mode.
5. The Script Gating tab: the markup that holds a script until consent, and the cookies to clear on withdrawal.
6. The Design tab: accent, background, text and button colors with a contrast check.

== Changelog ==

= 1.0.0 =

* Initial security-reviewed release.
* "Turn the banner off but keep my scripts running" (Settings > General). CrumbSignal shows no banner and runs your analytics, marketing and preferences scripts for every visitor without asking. It still respects a choice a visitor already made, and Global Privacy Control or Do Not Track when those options are on. For sites where consent isn't required (you remain responsible for compliance). Saved settings that lack the option keep the banner on.
