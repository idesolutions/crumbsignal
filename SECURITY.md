# CrumbSignal security policy

## Supported releases

Only the newest CrumbSignal release receives security fixes. Production sites should update promptly after testing in staging.

## Security design

- Settings are stored through the WordPress Settings API, which enforces capability and CSRF protections.
- Administrative output is escaped for its HTML context.
- URLs are restricted to HTTP and HTTPS schemes.
- Google identifiers are validated against their documented formats.
- Inline controlled scripts require WordPress's `unfiltered_html` capability.
- Inline script input containing a closing script tag is rejected.
- Custom CSS is length-limited and filtered to prevent executable or remote-loading constructs.
- Consent cookies are schema- and version-validated before use.
- Optional scripts are inert until the visitor grants their category.
- Revoking a previously granted category reloads the document after saving the denied state.
- Known and configured accessible first-party cookies are expired when their category is withdrawn. Browser JavaScript cannot remove third-party or HttpOnly cookies.
- CrumbSignal does not create public AJAX or REST endpoints and does not transmit consent records to an external service.
- Every script tag CrumbSignal prints (the consent bootstrap, Google tag loaders, and consent-gated scripts) goes through WordPress's `wp_print_script_tag()` / `wp_print_inline_script_tag()`, so a site enforcing a nonce-based Content-Security-Policy can add a `nonce` to all of them by hooking the core `wp_script_attributes` filter. CrumbSignal's frontend runtime reads that nonce back off the placeholder `<script type="text/plain">` element (via the `nonce` IDL property, since browsers hide the real value from `getAttribute()`) and carries it onto the script it activates after consent.

## Updates

The public release should use the WordPress.org update channel. CrumbSignal deliberately contains no custom remote updater, arbitrary package installer, telemetry SDK, or license bootstrapper. Before WordPress.org approval, releases should be installed manually from a verified package supplied by IDE Solutions LLC.

Verify package integrity before distribution. The release SHA-256 checksum is generated alongside each ZIP during packaging.

## Reporting a vulnerability

Do not disclose an unpatched vulnerability publicly. Send a reproducible report to IDE Solutions LLC through its official support channel, including the affected version, impact, prerequisites, and proof-of-concept steps. Never include real visitor data or production credentials.
