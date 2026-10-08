(function () {
  'use strict';

  function fieldByKey(key) {
    return document.querySelector('[name="crumbsignal_settings[' + key + ']"]');
  }

  function initPreview() {
    var widget = document.getElementById('crumbsignal-preview-widget');
    if (!widget) return;

    var textTargets = {
      heading: document.getElementById('crumbsignal-preview-heading'),
      description: document.getElementById('crumbsignal-preview-description'),
      accept_label: document.getElementById('crumbsignal-preview-accept'),
      reject_label: document.getElementById('crumbsignal-preview-reject'),
      customize_label: document.getElementById('crumbsignal-preview-customize')
    };
    Object.keys(textTargets).forEach(function (key) {
      var input = fieldByKey(key);
      var target = textTargets[key];
      if (!input || !target) return;
      input.addEventListener('input', function () { target.textContent = input.value; });
    });

    var colorProperties = {
      accent_color: '--crumbsignal-accent',
      background_color: '--crumbsignal-bg',
      text_color: '--crumbsignal-text'
    };
    Object.keys(colorProperties).forEach(function (key) {
      var input = fieldByKey(key);
      if (!input) return;
      input.addEventListener('input', function () {
        widget.style.setProperty(colorProperties[key], input.value);
      });
    });

    var position = fieldByKey('position');
    if (position) {
      position.addEventListener('change', function () {
        widget.className = 'crumbsignal crumbsignal--' + position.value;
      });
    }
  }

  function hexToRgb(hex) {
    var match = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex || '');
    return match ? { r: parseInt(match[1], 16), g: parseInt(match[2], 16), b: parseInt(match[3], 16) } : null;
  }

  function channelLuminance(channel) {
    var value = channel / 255;
    return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
  }

  function relativeLuminance(rgb) {
    return 0.2126 * channelLuminance(rgb.r) + 0.7152 * channelLuminance(rgb.g) + 0.0722 * channelLuminance(rgb.b);
  }

  // WCAG 2.x contrast ratio: https://www.w3.org/TR/WCAG21/#contrast-minimum
  function contrastRatio(hexA, hexB) {
    var a = hexToRgb(hexA);
    var b = hexToRgb(hexB);
    if (!a || !b) return null;
    var la = relativeLuminance(a) + 0.05;
    var lb = relativeLuminance(b) + 0.05;
    return la > lb ? la / lb : lb / la;
  }

  function initContrastReport() {
    var report = document.getElementById('crumbsignal-contrast-report');
    var l10n = window.CrumbSignalAdminL10n;
    if (!report || !l10n) return;

    var accent = fieldByKey('accent_color');
    var background = fieldByKey('background_color');
    var text = fieldByKey('text_color');
    var acceptLine = report.querySelector('[data-contrast="accept"]');
    var rejectLine = report.querySelector('[data-contrast="reject"]');

    function renderLine(target, label, ratio) {
      if (!target || ratio === null) return;
      var pass = ratio >= 4.5;
      var prefix = pass ? '✓ ' + l10n.contrastGood : '⚠ ' + l10n.contrastLow;
      var line = l10n.contrastLine.replace('%1$s', label).replace('%2$s', ratio.toFixed(2));
      target.textContent = prefix + ' — ' + line;
      target.classList.toggle('is-warning', !pass);
    }

    function render() {
      if (!accent || !background || !text) return;
      renderLine(acceptLine, l10n.acceptLabel, contrastRatio(accent.value, '#ffffff'));
      renderLine(rejectLine, l10n.rejectLabel, contrastRatio(text.value, background.value));
    }

    [accent, background, text].forEach(function (input) {
      if (input) input.addEventListener('input', render);
    });
    render();
  }

  document.addEventListener('DOMContentLoaded', function () {
    initPreview();
    initContrastReport();

    var tabs = document.querySelectorAll('.crumbsignal-tabs [role="tab"]');
    var panels = document.querySelectorAll('.crumbsignal-panel');

    function activate(tab) {
      tabs.forEach(function (item) {
        var selected = item === tab;
        item.classList.toggle('is-active', selected);
        item.setAttribute('aria-selected', selected ? 'true' : 'false');
        item.tabIndex = selected ? 0 : -1;
      });
      panels.forEach(function (panel) { panel.classList.toggle('is-active', panel.dataset.panel === tab.dataset.tab); });
      tab.focus();
    }

    tabs.forEach(function (tab, index) {
      tab.addEventListener('click', function () { activate(tab); });
      tab.addEventListener('keydown', function (event) {
        var target = { ArrowRight: 1, ArrowLeft: -1 }[event.key];
        if (!target) return;
        event.preventDefault();
        activate(tabs[(index + target + tabs.length) % tabs.length]);
      });
    });

    var list = document.getElementById('crumbsignal-script-list');
    var add = document.getElementById('crumbsignal-add-script');
    var template = document.getElementById('crumbsignal-script-template');
    if (add && list && template) {
      add.addEventListener('click', function () {
        var index = Date.now().toString();
        list.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, index));
      });
      list.addEventListener('click', function (event) {
        if (event.target.classList.contains('crumbsignal-remove-script')) {
          event.target.closest('.crumbsignal-script-row').remove();
        }
      });
    }
  });
}());

