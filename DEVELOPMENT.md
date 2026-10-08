# CrumbSignal development notes

CrumbSignal 1.0.0 is an original implementation created for IDE Solutions LLC. It does not incorporate or modify the source code of another consent-management plugin.

## Runtime flow

1. A small bootstrap runs at `wp_head` priority `-9999`.
2. It reads the first-party `crumbsignal_consent` cookie and validates the configured consent version.
3. Google Consent Mode v2 defaults are established before configured Google tags load.
4. The frontend controller displays the banner when no current decision exists.
5. A saved decision updates Google consent, pushes `crumbsignal_consent_update`, activates permitted scripts, and stores the decision.

## Public browser API

```js
window.CrumbSignal.getConsent();
window.CrumbSignal.openPreferences();
window.CrumbSignal.acceptAll();
window.CrumbSignal.rejectOptional();
```

The document also emits `crumbsignal:change` with the saved consent payload in `event.detail`.

## GuardRelay gate (1.1.0)

GuardRelay is a separate plugin that can say whether a visitor is a bot. The contract is small and generic:

* PHP: `guardrelay_analytics_gate_active()` returns true when visitors will be given a real yes/no.
  CrumbSignal calls it (if it exists) when printing the page and records the result as `gate` in the bootstrap config
  (`window.CrumbSignalState.config.gate`). Filter: `crumbsignal_gate_with_guardrelay`.
* Browser: `window.GuardRelay.verdict` is `{ allow: false, ... }` for a bot; the `guardrelay:verdict` document event
  fires when it is published (`event.detail` is the verdict). `window.GuardRelay.pending` is true until then.

When `gate` is true, `crumbsignal.js`:

1. waits for the verdict (already published, or the event) for at most 2.5 s, then proceeds as if the visitor is not a
   bot -- it never holds tags hostage to another plugin;
2. for a bot: hides the whole banner root, activates **no** tag (consented or not), stores **no** consent cookie
   (including for Global Privacy Control), sets `data-crumbsignal-bot` on `<html>`, fires `crumbsignal:bot`, and
   `CrumbSignal.isBot()` returns true;
3. for anyone else: behaves as before, with tag activation (and in advanced mode the held Google tags) released once the
   verdict is in. The banner is never delayed.

In advanced consent mode the Google tags are normally printed live at the top of the page. With the gate they are printed
as `type="text/plain" data-crumbsignal-gate="verdict"` and released by the script for non-bots regardless of consent,
which is what advanced mode does anyway. Strict mode already holds tags until consent, so only activation waits.

### Banner off (`no_banner`)

`no_banner` (default 0; phrased as "banner off" so a settings save that omits the key leaves the banner ON) prints no banner
markup and loads no banner CSS, but the bootstrap and `crumbsignal.js` still run: the consent state defaults to all categories
granted (Consent Mode `granted` defaults, `consent_source: 'no_banner'` in the dataLayer), a stored visitor choice still wins,
and Global Privacy Control / Do Not Track (when enabled) still reject. Scripts activate with no waiting unless the GuardRelay
gate is on, in which case a bot gets nothing. No consent cookie is ever written in this mode.
`window.CrumbSignal.openPreferences/acceptAll/rejectOptional` become harmless no-ops so third-party code that calls them keeps
working. "Enable the cookie banner" (`enabled`) is different: off, CrumbSignal prints nothing at all, tags included.

### Telling GuardRelay (`includes/integration-guardrelay.php`)

CrumbSignal registers itself on the `guardrelay_gate_consumers` filter (name => version) when it is enabled, so GuardRelay's
admin screens know a tag loader that honours the verdict is present. It is registered in the main plugin load, not in the
frontend class, because the frontend class does nothing in wp-admin, which is where GuardRelay shows its warning.

Tests: `node tests/js/gate.test.js` (browser logic against a fake page) and `vendor/bin/phpunit`
(`tests/Unit/FrontendGateTest.php`).

## Script gating markup

Inline script:

```html
<script type="text/plain" data-crumbsignal-category="analytics">
  // Runs after analytics consent.
</script>
```

External script:

```html
<script type="text/plain" data-crumbsignal-category="marketing" data-src="https://example.com/pixel.js"></script>
```

Supported public categories are `preferences`, `analytics`, and `marketing`.

## Developer filters

```php
add_filter( 'crumbsignal_settings', function ( array $settings ): array {
	$settings['policy_url'] = 'https://example.com/staging-privacy-policy/';
	return $settings;
} );
```

`crumbsignal_settings` fires once per page load, right before the frontend consent
runtime uses the merged settings (`CrumbSignal_Frontend::init()`). It only affects what
visitors see and never touches the stored `crumbsignal_settings` option or what the
wp-admin settings screen displays — so it's safe to use from a client-specific
mu-plugin or theme to override behavior per environment (e.g. a different policy URL on
staging, or programmatically adding an entry to `$settings['scripts']`) without that
override ever getting silently re-saved as if an admin had entered it by hand.

## Local tooling

```bash
composer install
composer test   # PHPUnit unit tests (tests/Unit) against CrumbSignal_Settings
composer lint   # phpcs against phpcs.xml.dist
```

The unit tests run without a WordPress install: `tests/wp-fakes.php` provides minimal,
behaviorally-accurate stand-ins for the handful of core sanitization functions
`CrumbSignal_Settings` calls, and [Brain Monkey](https://github.com/Brain-WP/BrainMonkey)
mocks the rest (`current_user_can`, `add_settings_error`, translation functions). They
cover `CrumbSignal_Settings::sanitize()` specifically because it is the plugin's main
attacker-facing surface. `phpcs.xml.dist` is tuned to this codebase's actual style
(terse docblocks, compact single-line admin templates) rather than the full stock
WordPress ruleset, so a clean run is meant to stay meaningful, not aspirational.

## Release checklist

- Test with a clean, supported WordPress installation.
- Test PHP 8.0 and the newest supported PHP release.
- Test keyboard-only banner and modal use.
- Verify no configured optional script creates cookies before consent.
- Validate Basic and Advanced Google Consent Mode with Tag Assistant.
- Test Accept All, Reject Optional, granular saving, withdrawal, expiration, GPC, and consent-version changes.
- Test common page caches and document required cache exclusions if a host rewrites HTML.
- Verify the Design tab's live preview and contrast warnings match the real frontend banner (colors, labels, and position), since the preview reuses the same CSS but is a separate DOM tree.
- If testing against a site with a nonce-based CSP, hook `wp_script_attributes` to add the nonce and confirm the bootstrap, Google tags, and gated scripts all execute after consent.
- Run `phpcs --standard=phpcs.xml.dist` and resolve findings before Plugin Check.
- Generate `languages/crumbsignal.pot` with `wp i18n make-pot . languages/crumbsignal.pot`.
- Run WordPress Plugin Check before submitting to WordPress.org.
- Update `Tested up to` only after completing the matching WordPress test.
- Distribute updates through WordPress.org after approval; do not add an unauthenticated custom updater.
- Generate and retain a SHA-256 checksum for every release ZIP.
