import test from 'node:test';
import assert from 'node:assert/strict';
import customizationQuote from '../../resources/js/customization-quote.js';
import productGallery from '../../resources/js/product-gallery.js';

test('quantity and size summary follow known sizes or general quantity', () => {
    const state = customizationQuote([{ slug: 'filipina', name: 'Filipina' }], { product: 'filipina', sizes: { S: 5, M: 10 }, unknown: false, quantity: 20 });
    assert.equal(state.total, 15);
    assert.equal(state.sizeSummary, 'S: 5 · M: 10');
    state.unknown = true;
    assert.equal(state.total, 20);
    state.changeModel();
    assert.deepEqual(state.sizes, {});
    assert.equal(state.color, '');
});

test('personalization carries product color and size from the gallery', () => {
    globalThis.window = { location: { origin: 'http://localhost' } };
    const state = productGallery([], { colors: [{ id: 2, name: 'Café' }], customizationUrl: 'http://localhost/personalizacion?product=filipina' });
    state.color = 2;
    state.size = 'M';
    const url = new URL(state.customizationUrl);
    assert.equal(url.searchParams.get('product'), 'filipina');
    assert.equal(url.searchParams.get('color'), 'Café');
    assert.equal(url.searchParams.get('size'), 'M');
    state.size = null;
    state.sizeAdvice = true;
    assert.equal(new URL(state.customizationUrl).searchParams.get('size_advice'), '1');
});
