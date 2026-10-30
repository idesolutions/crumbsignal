/*
 * Tests for the consent script in assets/js/crumbsignal.js: scripts stay inert until their category is accepted,
 * a stored choice or privacy signal is honoured, the banner can be switched off, and links added by the site owner
 * reopen the preferences.
 *
 *   node tests/js/consent.test.js
 *
 * Plain Node, no dependencies: the script runs in a vm context against a hand-made fake page.
 */
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const SOURCE = fs.readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', 'crumbsignal.js'), 'utf8');
const tick = () => new Promise((resolve) => setImmediate(resolve));

/** A fake element with just the surface crumbsignal.js touches. */
function element(extra = {}) {
  const listeners = {};
  return Object.assign({
    hidden: false,
    dataset: {},
    attrs: {},
    listeners,
    addEventListener(type, fn) { (listeners[type] = listeners[type] || []).push(fn); },
    querySelector() { return null; },
    querySelectorAll() { return []; },
    setAttribute(name, value) { this.attrs[name] = String(value); },
    focus() {},
    closest() { return null; },
    classList: { add() {}, remove() {} },
  }, extra);
}

/** An inert tag the page printed: type="text/plain" plus data-crumbsignal-* attributes. */
function inertScript(attributes, { src = null, text = 'tag()' } = {}) {
  const env = { activated: [] };
  const attrs = Object.assign({ type: 'text/plain' }, attributes);
  if (src) attrs['data-src'] = src;
  const names = Object.keys(attrs);
  const node = {
    attrs,
    attributes: names.map((name) => ({ name, value: attrs[name] })),
    dataset: Object.assign({}, src ? { src } : {}),
    textContent: text,
    nonce: '',
    nextSibling: null,
    getAttribute: (name) => (name in attrs ? attrs[name] : null),
    parentNode: null,
  };
  return node;
}

/**
 * A page. `config` is state.config, `consent` the stored categories (or null), `scripts` the inert tags in the
 * page.
 */
function page({ config = {}, consent = null, privacySignal = false, scripts = [], noRoot = false, categories: openCategories = null } = {}) {
  const env = { activated: [], cookies: [], timers: [], docListeners: {}, events: [], removed: [], dataLayer: [], gtag: [] };

  const categories = consent || openCategories || { necessary: true, preferences: false, analytics: false, marketing: false };
  const banner = element();
  const modal = element({ querySelector: () => null });
  const overlay = element({ hidden: true });
  const revisit = element({ hidden: true });
  const root = element({
    hidden: true,
    querySelector(selector) {
      return { '.crumbsignal__banner': banner, '.crumbsignal__modal': modal, '.crumbsignal__overlay': overlay, '.crumbsignal__revisit': revisit }[selector] || null;
    },
  });

  scripts.forEach((node) => {
    node.parentNode = { insertBefore: (created) => { env.activated.push(created); } };
  });

  const documentElement = element();

  const document = {
    readyState: 'complete',
    documentElement,
    activeElement: null,
    contains: () => false,
    getElementById: (id) => (!noRoot && id === 'crumbsignal-root' ? root : null),
    querySelectorAll(selector) {
      if (selector === 'script[type="text/plain"][data-crumbsignal-category]') return scripts.filter((s) => s.attrs['data-crumbsignal-category']);
      return [];
    },
    createElement() {
      const created = element({ attrs: {}, async: true, src: '', textContent: '' });
      created.addEventListener = (type, fn) => { if (type === 'load' || type === 'error') Promise.resolve().then(fn); };
      return created;
    },
    addEventListener(type, fn) { (env.docListeners[type] = env.docListeners[type] || []).push(fn); },
    removeEventListener(type, fn) {
      env.removed.push(type);
      env.docListeners[type] = (env.docListeners[type] || []).filter((f) => f !== fn);
    },
    dispatchEvent(event) { env.events.push(event); return true; },
  };
  Object.defineProperty(document, 'cookie', { get: () => '', set: (value) => { env.cookies.push(value); } });

  const window = {
    CrumbSignalState: {
      config: Object.assign({ duration: 180, cookieName: 'crumbsignal_consent', version: 1 }, config),
      consent: consent ? { categories } : null,
      categories,
      privacySignal,
    },
    CrumbSignalSettings: { cleanupRules: {} },
    dataLayer: env.dataLayer,
    gtag: (...args) => env.gtag.push(args),
    location: { protocol: 'https:', hostname: 'example.test', reload() {} },
    setTimeout: (fn, ms) => { env.timers.push({ fn, ms, cleared: false }); return env.timers.length; },
    clearTimeout: (id) => { if (env.timers[id - 1]) env.timers[id - 1].cleared = true; },
    CustomEvent: function (type, init) { this.type = type; this.detail = init && init.detail; },
  };
  window.window = window;

  env.run = () => vm.runInNewContext(SOURCE, { window, document, Promise, JSON, encodeURIComponent, location: window.location, CustomEvent: window.CustomEvent });
  env.window = window;
  env.root = root;
  env.banner = banner;
  env.modal = modal;
  env.documentElement = documentElement;
  env.eventTypes = () => env.events.map((e) => e.type);
  env.activatedTexts = () => env.activated.map((node) => node.textContent || node.src);
  return env;
}

const analyticsTag = () => inertScript({ 'data-crumbsignal-category': 'analytics' }, { text: 'analytics()' });
const googleTag = () => inertScript({ 'data-crumbsignal-category': 'google' }, { text: 'google()' });
const marketingTag = () => inertScript({ 'data-crumbsignal-category': 'marketing' }, { text: 'marketing()' });
const consentedAll = { necessary: true, preferences: true, analytics: true, marketing: true };

let passed = 0;
let failed = 0;

async function test(name, fn) {
  try {
    await fn();
    passed++;
    console.log('  PASS  ' + name);
  } catch (error) {
    failed++;
    console.log('  FAIL  ' + name + '\n        ' + String(error.message).split('\n').join('\n        '));
  }
}

(async () => {
  console.log('assets/js/crumbsignal.js: consent');

  await test('a stored choice activates exactly the categories that were accepted', async () => {
    const p = page({ consent: { necessary: true, preferences: false, analytics: true, marketing: false }, scripts: [analyticsTag(), marketingTag(), googleTag()] });
    p.run();
    await tick();
    assert.deepStrictEqual(p.activatedTexts().sort(), ['analytics()', 'google()'], 'analytics and the Google tag, not marketing');
    assert.strictEqual(p.banner.hidden, true, 'the banner stays out of the way');
    assert.strictEqual(p.timers.length, 0, 'nothing waits');
  });

  await test('with no stored choice, nothing runs and the banner is shown', async () => {
    const p = page({ scripts: [analyticsTag(), marketingTag(), googleTag()] });
    p.run();
    await tick();
    assert.deepStrictEqual(p.activated, []);
    assert.strictEqual(p.banner.hidden, false);
  });

  await test('Accept All records the choice and releases every gated script, once', async () => {
    const p = page({ scripts: [analyticsTag(), marketingTag()] });
    p.run();
    const actions = p.root.listeners.click[0];
    actions({ target: { closest: () => ({ dataset: { crumbsignalAction: 'accept' } }) } });
    actions({ target: { closest: () => ({ dataset: { crumbsignalAction: 'accept' } }) } });
    await tick();
    assert.deepStrictEqual(p.activatedTexts().sort(), ['analytics()', 'marketing()']);
    assert.ok(p.cookies.length >= 1, 'the choice is stored');
  });

  await test('Reject Optional records the choice and releases nothing', async () => {
    const p = page({ scripts: [analyticsTag(), marketingTag()] });
    p.run();
    p.root.listeners.click[0]({ target: { closest: () => ({ dataset: { crumbsignalAction: 'reject' } }) } });
    await tick();
    assert.deepStrictEqual(p.activated, []);
    assert.ok(p.cookies.length >= 1, 'the rejection is stored');
  });

  await test('a Global Privacy Control visitor is recorded as rejecting at once', async () => {
    const p = page({ privacySignal: true });
    p.run();
    assert.ok(p.cookies.length >= 1);
    assert.strictEqual(p.timers.length, 0);
  });

  // ---- The banner turned off: no markup is printed, the script only releases scripts ---------------

  const everything = { necessary: true, preferences: true, analytics: true, marketing: true };
  const nothing = { necessary: true, preferences: false, analytics: false, marketing: false };

  await test("no banner: the page's scripts run for everyone, with no banner and no cookie", async () => {
    const p = page({ config: { noBanner: true }, noRoot: true, categories: everything, scripts: [analyticsTag(), googleTag()] });
    p.run();
    await tick();
    assert.deepStrictEqual(p.activatedTexts().sort(), ['analytics()', 'google()']);
    assert.deepStrictEqual(p.cookies, [], 'no consent cookie is written');
    assert.strictEqual(p.timers.length, 0, 'and nothing waits');
    assert.strictEqual(typeof p.window.CrumbSignal.getConsent, 'function', 'the public API is still there');
    assert.deepStrictEqual(p.window.CrumbSignal.getConsent(), everything);
    assert.doesNotThrow(() => { p.window.CrumbSignal.openPreferences(); p.window.CrumbSignal.acceptAll(); p.window.CrumbSignal.rejectOptional(); }, 'API calls are harmless without a banner');
    assert.deepStrictEqual(p.cookies, [], 'even after those calls');
  });

  await test('no banner: a visitor who sends Global Privacy Control (or has opted out) still gets no optional scripts', async () => {
    const p = page({ config: { noBanner: true }, noRoot: true, categories: nothing, privacySignal: true, scripts: [analyticsTag(), googleTag()] });
    p.run();
    await tick();
    assert.deepStrictEqual(p.activated, []);
    assert.deepStrictEqual(p.cookies, [], 'and no cookie is written for them either');
  });

  await test('without the banner turned off, a page with no banner element does nothing (as before)', async () => {
    const p = page({ config: {}, noRoot: true, scripts: [analyticsTag()] });
    p.run();
    await tick();
    assert.deepStrictEqual(p.activated, []);
    assert.strictEqual(p.window.CrumbSignal, undefined, 'no API either: nothing was set up');
  });

  // ---- A link the site owner adds (e.g. in the footer) reopens the preferences ---------------------

  const clickOn = (p, matches) => {
    let prevented = false;
    const target = { closest: (selector) => (matches(selector) ? {} : null) };
    p.docListeners.click.forEach((fn) => fn({ target, preventDefault() { prevented = true; } }));
    return prevented;
  };

  await test('a footer link to #crumbsignal-preferences (or the helper class) opens the preferences', async () => {
    const p = page({ consent: consentedAll });
    p.run();
    assert.strictEqual(p.banner.hidden, true, 'banner is out of the way once a choice is stored');

    p.banner.hidden = false; // proves the click is what hides it again (openPreferences hides the banner)
    const prevented = clickOn(p, (selector) => selector.includes('#crumbsignal-preferences'));
    assert.strictEqual(prevented, true, 'the link does not also jump the page');
    assert.strictEqual(p.banner.hidden, true, 'preferences were opened');
  });

  await test('the click handler matches links by their ending, so "/#..." and full URLs work', async () => {
    const p = page({ consent: consentedAll });
    p.run();
    let selector = '';
    p.docListeners.click.forEach((fn) => fn({ target: { closest: (s) => { selector = s; return null; } }, preventDefault() {} }));
    assert.ok(selector.includes('a[href$="#crumbsignal-preferences"]'), selector);
  });

  await test('arriving on a URL with #crumbsignal-preferences opens the preferences', async () => {
    const p = page({ consent: consentedAll });
    p.window.location.hash = '#crumbsignal-preferences';
    p.modal.hidden = true;
    p.run();
    assert.strictEqual(p.modal.hidden, false, 'preferences were opened on load');

    const other = page({ consent: consentedAll });
    other.window.location.hash = '#something-else';
    other.modal.hidden = true;
    other.run();
    assert.strictEqual(other.modal.hidden, true, 'a different hash does nothing');
  });

  await test('clicks on anything else are left alone', async () => {
    const p = page({ consent: consentedAll });
    p.run();
    p.banner.hidden = false;
    const prevented = clickOn(p, () => false);
    assert.strictEqual(prevented, false);
    assert.strictEqual(p.banner.hidden, false, 'nothing opened');
  });

  console.log(`\n${passed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
})();
