(function () {
  'use strict';

  var state = window.CrumbSignalState;
  var settings = window.CrumbSignalSettings || {};
  if (!state) return;

  var root;
  var banner;
  var modal;
  var overlay;
  var revisit;
  var previousFocus;

  function categories(value) {
    return { necessary: true, preferences: value, analytics: value, marketing: value };
  }

  function setCookie(payload) {
    var maxAge = state.config.duration * 86400;
    var secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = state.config.cookieName + '=' + encodeURIComponent(JSON.stringify(payload)) + '; Path=/; Max-Age=' + maxAge + '; SameSite=Lax' + secure;
  }

  function updateGoogle(selected) {
    window.gtag('consent', 'update', {
      analytics_storage: selected.analytics ? 'granted' : 'denied',
      ad_storage: selected.marketing ? 'granted' : 'denied',
      ad_user_data: selected.marketing ? 'granted' : 'denied',
      ad_personalization: selected.marketing ? 'granted' : 'denied',
      functionality_storage: selected.preferences ? 'granted' : 'denied',
      personalization_storage: selected.preferences ? 'granted' : 'denied',
      security_storage: 'granted'
    });
  }

  function removeCookie(name) {
    var expires = 'Thu, 01 Jan 1970 00:00:00 GMT';
    var hostname = window.location.hostname;
    var domains = ['', hostname, '.' + hostname];
    domains.forEach(function (domain) {
      var domainPart = domain ? '; Domain=' + domain : '';
      document.cookie = name + '=; Path=/; Expires=' + expires + '; Max-Age=0; SameSite=Lax' + domainPart;
    });
  }

  function cleanRevokedCookies(previous, selected) {
    var rules = settings.cleanupRules || {};
    var cookieNames = document.cookie.split(';').map(function (part) { return part.split('=')[0].trim(); }).filter(Boolean);
    ['preferences', 'analytics', 'marketing'].forEach(function (category) {
      if (!previous[category] || selected[category]) return;
      (rules[category] || []).forEach(function (rule) {
        var wildcard = rule.slice(-1) === '*';
        var prefix = wildcard ? rule.slice(0, -1) : rule;
        cookieNames.forEach(function (name) {
          if ((wildcard && name.indexOf(prefix) === 0) || (!wildcard && name === prefix)) removeCookie(name);
        });
      });
    });
  }

  function activateMatching(selector, isAllowed) {
    var chain = Promise.resolve();
    document.querySelectorAll(selector).forEach(function (blocked) {
      if (!isAllowed(blocked) || blocked.dataset.crumbsignalActivated === 'true') return;
      blocked.dataset.crumbsignalActivated = 'true';
      chain = chain.then(function () {
        return new Promise(function (resolve) {
          var active = document.createElement('script');
          Array.prototype.forEach.call(blocked.attributes, function (attribute) {
            if (['type', 'data-src', 'data-crumbsignal-category', 'data-crumbsignal-id', 'data-crumbsignal-activated', 'nonce'].indexOf(attribute.name) === -1) {
              active.setAttribute(attribute.name, attribute.value);
            }
          });
          // A CSP nonce is only readable through the IDL property: the browser
          // hides the real value from getAttribute()/attributes once a script
          // element has been parsed, to stop it leaking via DOM inspection.
          if (blocked.nonce) active.nonce = blocked.nonce;
          if (blocked.dataset.src) {
            active.async = false;
            active.src = blocked.dataset.src;
            active.addEventListener('load', resolve, { once: true });
            active.addEventListener('error', resolve, { once: true });
          } else {
            active.textContent = blocked.textContent;
          }
          blocked.parentNode.insertBefore(active, blocked.nextSibling);
          if (!blocked.dataset.src) resolve();
        });
      });
    });
    return chain;
  }

  function activateNow(selected) {
    return activateMatching('script[type="text/plain"][data-crumbsignal-category]', function (blocked) {
      var category = blocked.getAttribute('data-crumbsignal-category');
      return category === 'google' ? (selected.analytics || selected.marketing) : selected[category];
    });
  }

  function persist(selected, source) {
    var previous = state.categories;
    var revoked = !!((previous.analytics && !selected.analytics) || (previous.marketing && !selected.marketing) || (previous.preferences && !selected.preferences));
    var payload = {
      version: state.config.version,
      timestamp: new Date().toISOString(),
      categories: selected,
      source: source
    };
    state.categories = selected;
    state.consent = payload;
    setCookie(payload);
    updateGoogle(selected);
    window.dataLayer.push({
      event: 'crumbsignal_consent_update',
      consent_analytics: !!selected.analytics,
      consent_marketing: !!selected.marketing,
      consent_preferences: !!selected.preferences,
      consent_source: source
    });
    activateNow(selected);
    closeAll();
    if (revisit) revisit.hidden = false;
    document.dispatchEvent(new CustomEvent('crumbsignal:change', { detail: payload }));
    if (revoked) {
      cleanRevokedCookies(previous, selected);
      window.setTimeout(function () { window.location.reload(); }, 150);
    }
  }

  function openPreferences() {
    previousFocus = document.activeElement;
    ['preferences', 'analytics', 'marketing'].forEach(function (key) {
      var input = modal.querySelector('[data-crumbsignal-category-input="' + key + '"]');
      if (input) input.checked = !!state.categories[key];
    });
    banner.hidden = true;
    overlay.hidden = false;
    modal.hidden = false;
    document.documentElement.classList.add('crumbsignal-open');
    var first = modal.querySelector('button, input');
    if (first) first.focus();
  }

  function closeAll() {
    if (banner) banner.hidden = true;
    if (modal) modal.hidden = true;
    if (overlay) overlay.hidden = true;
    document.documentElement.classList.remove('crumbsignal-open');
    if (previousFocus && document.contains(previousFocus)) previousFocus.focus();
  }

  function saveCustom() {
    var selected = categories(false);
    ['preferences', 'analytics', 'marketing'].forEach(function (key) {
      var input = modal.querySelector('[data-crumbsignal-category-input="' + key + '"]');
      selected[key] = !!(input && input.checked);
    });
    persist(selected, 'custom');
  }

  function trapFocus(event) {
    if (event.key === 'Escape') {
      closeAll();
      return;
    }
    if (event.key !== 'Tab' || modal.hidden) return;
    var items = modal.querySelectorAll('button:not([disabled]), input:not([disabled])');
    if (!items.length) return;
    var first = items[0];
    var last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  }

  // The banner is switched off: nothing is printed for visitors to see. This script's only job is to release the
  // page's scripts.
  function initWithoutBanner() {
    activateNow(state.categories);
    window.CrumbSignal = {
      getConsent: function () { return JSON.parse(JSON.stringify(state.categories)); },
      openPreferences: function () {},
      acceptAll: function () {},
      rejectOptional: function () {}
    };
  }

  function init() {
    root = document.getElementById('crumbsignal-root');
    if (!root) {
      if (state.config.noBanner) initWithoutBanner();
      return;
    }
    banner = root.querySelector('.crumbsignal__banner');
    modal = root.querySelector('.crumbsignal__modal');
    overlay = root.querySelector('.crumbsignal__overlay');
    revisit = root.querySelector('.crumbsignal__revisit');
    root.hidden = false;

    root.addEventListener('click', function (event) {
      var button = event.target.closest('[data-crumbsignal-action]');
      if (!button) return;
      var action = button.dataset.crumbsignalAction;
      if (action === 'accept') persist(categories(true), 'accept_all');
      if (action === 'reject') persist(categories(false), 'reject_optional');
      if (action === 'customize') openPreferences();
      if (action === 'save') saveCustom();
      if (action === 'close') closeAll();
    });
    modal.addEventListener('keydown', trapFocus);

    // Any link or button the site owner adds (e.g. in the footer) can reopen the preferences. Links are matched by
    // their ending so "/#crumbsignal-preferences" and full URLs, which menus and page builders often produce, work too.
    document.addEventListener('click', function (event) {
      var trigger = event.target && event.target.closest && event.target.closest('a[href$="#crumbsignal-preferences"], .crumbsignal-open-preferences');
      if (!trigger) return;
      event.preventDefault();
      openPreferences();
    });

    if (state.consent || state.privacySignal) {
      banner.hidden = true;
      if (revisit) revisit.hidden = false;
      activateNow(state.categories);
      if (state.privacySignal && !state.consent) persist(categories(false), 'privacy_signal');
    } else {
      banner.hidden = false;
      if (revisit) revisit.hidden = true;
    }

    window.CrumbSignal = {
      getConsent: function () { return JSON.parse(JSON.stringify(state.categories)); },
      openPreferences: openPreferences,
      acceptAll: function () { persist(categories(true), 'api'); },
      rejectOptional: function () { persist(categories(false), 'api'); }
    };

    // Arriving on a URL that ends in #crumbsignal-preferences (e.g. from a link on another page) opens them at once.
    if (window.location && window.location.hash === '#crumbsignal-preferences') openPreferences();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
}());
