/*
 * Tests for the GuardRelay gate in assets/js/crumbsignal.js: on a page built to listen for GuardRelay, tags
 * wait for its verdict on the visitor; a bot is shown no banner and given no tags and no consent cookie; a
 * person is unaffected; and on a page that isn't listening, nothing changes and nothing waits.
 *
 *   node tests/js/gate.test.js
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
 * A page. `config` is state.config (set gate: true for a page that listens for GuardRelay), `consent` the
 * stored categories (or null), `scripts` the inert tags in the page, `guardrelay` an optional existing
 * window.GuardRelay object.
 */
function page({ config = {}, consent = null, privacySignal = false, scripts = [], guardrelay = undefined, noRoot = false, categories: openCategories = null } = {}) {
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
      if (selector === 'script[type="text/plain"][data-crumbsignal-gate]') return scripts.filter((s) => s.attrs['data-crumbsignal-gate']);
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
    GuardRelay: guardrelay,
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
  env.documentElement = documentElement;
  env.verdict = (verdict) => {
    window.GuardRelay = Object.assign(window.GuardRelay || {}, { verdict, bot: verdict.allow === false, pending: false });
    (env.docListeners['guardrelay:verdict'] || []).slice().forEach((fn) => fn({ detail: verdict }));
  };
  env.eventTypes = () => env.events.map((e) => e.type);
  env.activatedTexts = () => env.activated.map((node) => node.textContent || node.src);
  return env;
}

const analyticsTag = () => inertScript({ 'data-crumbsignal-category': 'analytics' }, { text: 'analytics()' });
const googleTag = () => inertScript({ 'data-crumbsignal-category': 'google' }, { text: 'google()' });
const gatedTag = (text = 'gated()') => inertScript({ 'data-crumbsignal-gate': 'verdict', id: 'crumbsignal-gtm' }, { text });
const human = { allow: true, wouldExclude: false, mode: 'exclude' };
const bot = { allow: false, wouldExclude: true, mode: 'exclude' };
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
  console.log('assets/js/crumbsignal.js: GuardRelay gate');

  await test('a page that is not listening for GuardRelay behaves exactly as before: nothing waits', async () => {
    const p = page({ consent: consentedAll, scripts: [analyticsTag(), googleTag()] });
    p.run();
    await tick();
    assert.deepStrictEqual(p.activatedTexts(), ['analytics()', 'google()'], 'consented tags activate straight away, with no waiting');
    assert.strictEqual(p.root.hidden, false, 'banner root shown');
    assert.strictEqual(p.timers.length, 0, 'no timer started');
    assert.strictEqual(p.docListeners['guardrelay:verdict'], undefined, 'no listener for a verdict');
    assert.strictEqual(p.documentElement.attrs['data-crumbsignal-bot'], undefined);
    assert.strictEqual(typeof p.window.CrumbSignal.isBot, 'function');
    assert.strictEqual(p.window.CrumbSignal.isBot(), false);
  });

  await test('...even if a bot verdict happens to exist, a page that is not listening ignores it', async () => {
    const p = page({ consent: consentedAll, scripts: [analyticsTag()], guardrelay: { verdict: bot } });
    p.run();
    await tick();
    assert.deepStrictEqual(p.activatedTexts(), ['analytics()']);
    assert.strictEqual(p.root.hidden, false);
  });

  await test('listening, verdict already in: a person gets the banner and their consented tags, as normal', async () => {
    const p = page({ config: { gate: true }, consent: consentedAll, scripts: [analyticsTag(), gatedTag()], guardrelay: { verdict: human } });
    p.run();
    await tick();
    assert.deepStrictEqual(p.activatedTexts().sort(), ['analytics()', 'gated()'], 'consented tag and the gated Google tag');
    assert.strictEqual(p.root.hidden, false);
    assert.strictEqual(p.window.CrumbSignal.isBot(), false);
  });

  await test('listening, verdict already in: a person who has not consented gets the gated tag (advanced mode) but not the consent-gated ones', async () => {
    const p = page({ config: { gate: true }, scripts: [analyticsTag(), gatedTag()], guardrelay: { verdict: human } });
    p.run();
    await tick();
    assert.deepStrictEqual(p.activatedTexts(), ['gated()'], 'only the tag that advanced mode loads without consent');
    assert.strictEqual(p.banner.hidden, false, 'and the banner is shown');
  });

  await test('listening, verdict already in: a BOT is shown nothing and given nothing', async () => {
    const p = page({ config: { gate: true }, consent: consentedAll, scripts: [analyticsTag(), googleTag(), gatedTag()], guardrelay: { verdict: bot } });
    p.run();
    await tick();
    assert.deepStrictEqual(p.activated, [], 'not one tag activated, even though it had "consented"');
    assert.strictEqual(p.root.hidden, true, 'no banner, no preferences, no revisit button');
    assert.strictEqual(p.documentElement.attrs['data-crumbsignal-bot'], '', 'the page is marked');
    assert.ok(p.eventTypes().includes('crumbsignal:bot'), 'crumbsignal:bot event fired');
    assert.strictEqual(p.window.CrumbSignal.isBot(), true);
    assert.deepStrictEqual(p.cookies, [], 'no consent cookie written');
  });

  await test('listening, verdict not in yet: tags wait, the banner does not, and a person is released when the answer arrives', async () => {
    const p = page({ config: { gate: true }, consent: consentedAll, scripts: [analyticsTag(), gatedTag()], guardrelay: { pending: true } });
    p.run();
    await tick();
    assert.deepStrictEqual(p.activated, [], 'nothing runs while the answer is pending');
    assert.strictEqual(p.root.hidden, false, 'the banner is not delayed');
    assert.strictEqual(p.timers.length, 1, 'a give-up timer is running');

    p.verdict(human);
    await tick();
    assert.deepStrictEqual(p.activatedTexts().sort(), ['analytics()', 'gated()']);
    assert.ok(p.timers[0].cleared, 'the timer is cancelled');
    assert.ok(p.removed.includes('guardrelay:verdict'), 'and the listener removed');
  });

  await test('listening, verdict not in yet: a bot is released to nothing, and the banner is taken away', async () => {
    const p = page({ config: { gate: true }, consent: consentedAll, scripts: [analyticsTag(), gatedTag()], guardrelay: { pending: true } });
    p.run();
    await tick();
    assert.strictEqual(p.root.hidden, false, 'shown at first');

    p.verdict(bot);
    await tick();
    assert.deepStrictEqual(p.activated, []);
    assert.strictEqual(p.root.hidden, true, 'then hidden');
    assert.strictEqual(p.window.CrumbSignal.isBot(), true);
  });

  await test('FAILS OPEN: no answer within the time limit means carry on as normal', async () => {
    const p = page({ config: { gate: true }, consent: consentedAll, scripts: [analyticsTag(), gatedTag()], guardrelay: { pending: true } });
    p.run();
    await tick();
    assert.deepStrictEqual(p.activated, []);
    assert.strictEqual(p.timers[0].ms, 2500, 'gives up after 2.5 seconds');

    p.timers[0].fn();
    await tick();
    assert.deepStrictEqual(p.activatedTexts().sort(), ['analytics()', 'gated()'], 'tags are released');
    assert.strictEqual(p.root.hidden, false);
    assert.ok(p.removed.includes('guardrelay:verdict'), 'listener removed');

    p.verdict(bot);
    await tick();
    assert.strictEqual(p.root.hidden, false, 'a very late "bot" cannot take the banner away after the fact');
  });

  await test('a verdict that is not shaped as expected is treated as a person', async () => {
    for (const odd of [{}, { allow: true }, { allow: null }, { error: true, allow: true }]) {
      const p = page({ config: { gate: true }, consent: consentedAll, scripts: [analyticsTag()], guardrelay: { pending: true } });
      p.run();
      p.verdict(odd);
      await tick();
      assert.deepStrictEqual(p.activatedTexts(), ['analytics()'], JSON.stringify(odd));
    }
  });

  await test('a visitor who clicks Accept while the answer is pending gets their tags only once they are known to be a person', async () => {
    const p = page({ config: { gate: true }, scripts: [analyticsTag()], guardrelay: { pending: true } });
    p.run();
    const actions = p.root.listeners.click[0];
    const accept = { closest: () => ({ dataset: { crumbsignalAction: 'accept' } }) };
    actions({ target: accept });
    await tick();
    assert.deepStrictEqual(p.activated, [], 'consent saved, tag waits');
    assert.ok(p.cookies.length >= 1, 'the choice itself is recorded');

    p.verdict(human);
    await tick();
    assert.deepStrictEqual(p.activatedTexts(), ['analytics()']);
  });

  await test('a Global Privacy Control visitor: a person has the choice recorded; a bot does not get a cookie', async () => {
    const person = page({ config: { gate: true }, privacySignal: true, guardrelay: { pending: true } });
    person.run();
    person.verdict(human);
    await tick();
    assert.ok(person.cookies.length >= 1, 'recorded for a person');

    const robot = page({ config: { gate: true }, privacySignal: true, guardrelay: { pending: true } });
    robot.run();
    robot.verdict(bot);
    await tick();
    assert.deepStrictEqual(robot.cookies, [], 'nothing stored for a bot');
  });

  await test('without the gate, a Global Privacy Control visitor is recorded at once, as before', async () => {
    const p = page({ privacySignal: true });
    p.run();
    assert.ok(p.cookies.length >= 1);
    assert.strictEqual(p.timers.length, 0);
  });

  await test('tags activate once only, however many times the gate is consulted', async () => {
    const p = page({ config: { gate: true }, consent: consentedAll, scripts: [analyticsTag(), gatedTag()], guardrelay: { verdict: human } });
    p.run();
    await tick();
    p.verdict(human);
    await tick();
    assert.strictEqual(p.activated.length, 2);
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

  await test('no banner + GuardRelay: a person gets their scripts and the held Google tag; a bot gets nothing', async () => {
    const person = page({ config: { noBanner: true, gate: true }, noRoot: true, categories: everything, scripts: [analyticsTag(), gatedTag()], guardrelay: { pending: true } });
    person.run();
    await tick();
    assert.deepStrictEqual(person.activated, [], 'held while the verdict is pending');
    person.verdict(human);
    await tick();
    assert.deepStrictEqual(person.activatedTexts().sort(), ['analytics()', 'gated()']);

    const robot = page({ config: { noBanner: true, gate: true }, noRoot: true, categories: everything, scripts: [analyticsTag(), gatedTag()], guardrelay: { pending: true } });
    robot.run();
    robot.verdict(bot);
    await tick();
    assert.deepStrictEqual(robot.activated, [], 'a bot is given nothing');
    assert.strictEqual(robot.documentElement.attrs['data-crumbsignal-bot'], '');
    assert.ok(robot.eventTypes().includes('crumbsignal:bot'));
    assert.strictEqual(robot.window.CrumbSignal.isBot(), true);
    assert.deepStrictEqual(robot.cookies, []);
  });

  await test('no banner + GuardRelay: no answer in time means carry on (fail open)', async () => {
    const p = page({ config: { noBanner: true, gate: true }, noRoot: true, categories: everything, scripts: [analyticsTag(), gatedTag()], guardrelay: { pending: true } });
    p.run();
    await tick();
    assert.deepStrictEqual(p.activated, []);
    p.timers[0].fn();
    await tick();
    assert.deepStrictEqual(p.activatedTexts().sort(), ['analytics()', 'gated()']);
  });

  await test('without the banner turned off, a page with no banner element does nothing (as before)', async () => {
    const p = page({ config: {}, noRoot: true, scripts: [analyticsTag()] });
    p.run();
    await tick();
    assert.deepStrictEqual(p.activated, []);
    assert.strictEqual(p.window.CrumbSignal, undefined, 'no API either: nothing was set up');
  });

  console.log(`\n${passed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
})();
