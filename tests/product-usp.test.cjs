const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

test('product USP updates on variation selection and resets to its original range', () => {
  const callbacks = {};
  const element = { dataset: { rate: '2', label: 'Ontvang {{points}} Smaakpunten', initialText: 'Ontvang 20 - 60 Smaakpunten' }, textContent: '' };
  const document = { querySelectorAll(selector) { return selector === '[data-lewm-product-usp]' ? [element] : []; }, addEventListener() {} };
  const jquery = { on(event, selector, callback) { callbacks[event] = callback; return jquery; } };
  const window = { lewWooCommerce: { pageType: 'product', product: { minPrice: 10 }, pointsLocale: 'nl-NL' }, jQuery() { return jquery; }, addEventListener() {}, dispatchEvent() {} };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../assets/js/storefront-bridge.js'), 'utf8'), { window, document, CustomEvent: class {} });
  callbacks.found_variation(null, { display_price: 59.95, lew_award_price: 49.55, variation_id: 2 });
  assert.equal(element.textContent, 'Ontvang 119 Smaakpunten');
  callbacks['reset_data hide_variation']();
  assert.equal(element.textContent, 'Ontvang 20 - 60 Smaakpunten');
});
