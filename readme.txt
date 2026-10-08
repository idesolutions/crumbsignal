=== CrumbSignal – Cookie Consent & Consent Mode ===
Contributors: idesolutionsllc
Tags: cookie consent, consent mode, google tag manager, analytics, privacy
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Clear consent. Clean signals. Add a customizable cookie banner, control optional scripts, and connect consent choices to Google Consent Mode v2.

== Description ==
CrumbSignal is a self-hosted WordPress consent manager. It stores consent in a first-party cookie, keeps configured scripts inactive until the matching category is accepted, and communicates visitor choices to Google tags.

= Features =

* Accept all, reject optional, and granular preferences
* Necessary, preferences, analytics, and marketing categories
* Google Consent Mode v2 defaults and updates
* Google Tag Manager, GA4, and Google Ads IDs
* Basic and advanced Google consent configurations
* dataLayer events for custom GTM triggers
* Category-based external and inline script controls
* First-party cookie cleanup rules when consent is withdrawn
* Global Privacy Control support
* Works with GuardRelay (separate plugin): a visitor GuardRelay identifies as a bot gets no banner, no analytics tags and no consent cookie
* Optional "banner off" mode: show no banner but keep your scripts running (for sites that don't need consent, or to use GuardRelay without a banner)
* Accessible modal, focus management, and reduced-motion support
* Custom colors, copy, position, and CSS
* No account, external dashboard, or pageview limits

CrumbSignal is a technical consent tool. Correct configuration and accurate privacy disclosures remain the website owner's responsibility. This plugin does not provide legal advice or guarantee compliance with any law.

== Installation ==
1. Upload the `crumbsignal` folder to `/wp-content/plugins/` or install the ZIP from Plugins > Add New > Upload Plugin.
2. Activate CrumbSignal.
3. Open Settings > CrumbSignal.
4. Configure banner copy, Google tags, and any scripts that require consent.
5. Test Accept, Reject, and Customize in a private browser window before publishing.

== Frequently Asked Questions ==
= Does CrumbSignal scan my website for cookies? =
No. Version 1 focuses on transparent, deterministic control of configured scripts. Automated scanners can miss scripts or classify them incorrectly.

= Does Google Tag Manager load before consent? =
In Advanced Consent Mode, the container may load with denied consent defaults. In Basic Consent Mode, CrumbSignal only loads the configured GTM container after analytics or marketing consent. Consent defaults are established before either mode loads Google tags.

= What dataLayer events are available? =
`crumbsignal_ready` fires after the default or stored consent state is established. `crumbsignal_consent_update` fires when the visitor saves a choice. Both include consent booleans for analytics, marketing, and preferences.

= Can I control scripts added outside CrumbSignal? =
Yes. Change the script type to `text/plain`, add `data-crumbsignal-category="analytics"` (or preferences/marketing), and move an external URL from `src` to `data-src`.

= Will page caching affect the cookie banner or consent signals? =
CrumbSignal reads and writes consent entirely in the visitor's browser: a first-party cookie plus a small bootstrap script that runs before any other script, both served as part of the normal (cacheable) page HTML. A correctly configured cache does not need to exclude pages using CrumbSignal. The one thing to check is whether your host or a JS-optimization plugin rewrites, defers, combines, or strips inline `<script>` tags — if so, exclude the `crumbsignal-consent-bootstrap` script (and the consent-gated `data-crumbsignal-category` scripts) from that optimization, since they must run unmodified and in order.

== Screenshots ==

1. The visitor-facing consent banner with Accept, Reject, and Customize actions.
2. The granular cookie preferences panel, opened from Customize or the revisit button.
3. The CrumbSignal settings screen: General tab (consent duration, banner position, GPC/DNT).
4. The Google & GTM tab: GTM/GA4/Ads IDs and Basic vs. Advanced Consent Mode.
5. The Script Manager tab: adding a category-gated third-party script.
6. The Design tab: accent/background/text colors and custom CSS.

== Changelog ==

= 1.1.0 =
* New: optional GuardRelay integration. When GuardRelay's analytics check is set to Exclude, tags wait briefly for its verdict on the visitor (and give up after 2.5 seconds, so a problem there can never cost a site its tags). A visitor identified as a bot is shown no banner, no analytics or marketing tags are activated for it, and no consent cookie is stored. Advanced-mode Google tags are held until the verdict is in. Sites without GuardRelay, or with it in Observe mode, are unchanged and nothing waits.
* New: "Turn the banner off but keep my scripts running" (Settings > General). CrumbSignal shows no banner and runs your analytics, marketing and preferences scripts for every visitor without asking. It still respects a choice a visitor already made, and Global Privacy Control or Do Not Track when those options are on. For sites where consent isn't required (you remain responsible for compliance), and for keeping bots out of analytics with GuardRelay without showing anyone a banner. Saved settings that lack the option keep the banner on.
* New: CrumbSignal tells GuardRelay it can hold back tags (filter `guardrelay_gate_consumers`), so GuardRelay does not warn that bots will still be counted.
* New: `window.CrumbSignal.isBot()`, the `crumbsignal:bot` event, and `data-crumbsignal-bot` on the html element.
* New filter: `crumbsignal_gate_with_guardrelay` (return false to ignore GuardRelay).

= 1.0.0 =

* Initial security-reviewed release.
