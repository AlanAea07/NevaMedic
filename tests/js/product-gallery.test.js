import test from 'node:test';
import assert from 'node:assert/strict';
import productGallery from '../../resources/js/product-gallery.js';

const photos = [
    { id: 1, color: null }, { id: 2, color: null },
    { id: 3, color: 10 }, { id: 4, color: 10 }, { id: 5, color: 20 },
];

test('general views loop in both directions and colors have independent galleries', () => {
    const gallery = productGallery(photos);
    gallery.init();
    assert.equal(gallery.selected, 1);
    gallery.step(-1);
    assert.equal(gallery.selected, 2);
    gallery.step(1);
    assert.equal(gallery.selected, 1);
    gallery.selectColor(10);
    assert.deepEqual(gallery.visibleImages.map(photo => photo.id), [3, 4]);
    gallery.failed = true;
    gallery.step(1);
    assert.equal(gallery.selected, 4);
    assert.equal(gallery.position, 2);
    assert.equal(gallery.failed, false);
    gallery.selectImage(2);
    assert.equal(gallery.selected, 4);
    gallery.selectColor(20);
    gallery.step(1);
    assert.equal(gallery.selected, 5);
    gallery.selectColor(null);
    assert.equal(gallery.selected, 1);
});

test('catalog cards browse all supplied photos while preserving independent state', () => {
    const card = productGallery(photos, { browseAll: true });
    const anotherCard = productGallery(photos, { browseAll: true });
    card.init();
    card.step(-1);
    assert.equal(card.selected, 5);
    assert.equal(card.visibleImages.length, 5);
    assert.equal(anotherCard.selected, 1);
});

test('missing and color-only galleries remain usable', () => {
    const empty = productGallery([]);
    empty.init();
    empty.step(1);
    assert.equal(empty.selected, null);
    assert.equal(empty.position, 0);
    const colorOnly = productGallery(photos.slice(2));
    colorOnly.init();
    assert.equal(colorOnly.color, 10);
    assert.equal(colorOnly.visibleImages.length, 2);
    colorOnly.selectColor(99);
    assert.equal(colorOnly.current, undefined);
    colorOnly.selectColor(10);
    assert.equal(colorOnly.selected, 3);
});

test('photo navigation does not change the requested size or selected color', () => {
    globalThis.window = { location: { origin: 'https://example.test' } };
    const gallery = productGallery(photos, { colors: [{ id: 10, name: 'Amarillo' }], quoteUrl: '/contacto?product=prenda&direct=1' });
    gallery.init();
    gallery.size = 'M';
    gallery.selectColor(10);
    gallery.step(1);
    const url = new URL(gallery.quoteUrl);
    assert.equal(url.searchParams.get('size'), 'M');
    assert.equal(url.searchParams.get('color'), 'Amarillo');
    assert.equal(url.searchParams.get('direct'), '1');
});
