export default (images, options = {}) => ({
    images,
    size: null,
    sizeAdvice: false,
    color: null,
    selected: images[0]?.id ?? null,
    failed: false,
    init() {
        const first = this.images[0];
        if (first?.color) this.color = first.color;
    },
    get visibleImages() {
        return options.browseAll ? this.images : this.images.filter(image => image.color === this.color);
    },
    get position() {
        return this.visibleImages.findIndex(image => image.id === this.selected) + 1;
    },
    get current() {
        return this.images.find(image => image.id === this.selected);
    },
    get colorName() {
        return options.colors?.find(color => color.id === this.color)?.name ?? '';
    },
    get quoteUrl() {
        const url = new URL(options.quoteUrl, window.location.origin);
        if (this.size) url.searchParams.set('size', this.size);
        if (this.colorName) url.searchParams.set('color', this.colorName);
        if (this.sizeAdvice) url.searchParams.set('size_advice', '1');
        return url.href;
    },
    get customizationUrl() {
        const url = new URL(options.customizationUrl, window.location.origin);
        if (this.size) url.searchParams.set('size', this.size);
        if (this.colorName) url.searchParams.set('color', this.colorName);
        if (this.sizeAdvice) url.searchParams.set('size_advice', '1');
        return url.href;
    },
    get selectionSummary() {
        return [this.size ? `Talla ${this.size}` : null, this.colorName, this.sizeAdvice ? 'Necesito asesoría de talla' : null].filter(Boolean).join(' · ');
    },
    selectColor(id) {
        this.color = id;
        this.selected = this.visibleImages[0]?.id ?? null;
        this.failed = false;
    },
    selectImage(id) {
        if (!this.visibleImages.some(image => image.id === id)) return;
        this.selected = id;
        this.failed = false;
    },
    step(direction) {
        const photos = this.visibleImages;
        if (photos.length < 2) return;
        const index = photos.findIndex(image => image.id === this.selected);
        this.selectImage(photos[(index + direction + photos.length) % photos.length].id);
    },
});
