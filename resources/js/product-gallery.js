export default (images, options = {}) => ({
    images,
    size: null,
    color: null,
    selected: images[0]?.id ?? null,
    failed: false,
    init() {
        const first = this.images[0];
        if (first?.color) this.color = first.color;
    },
    get visibleImages() {
        return this.images.filter(image => image.color === null || image.color === this.color);
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
        return url.href;
    },
    selectColor(id) {
        this.color = id;
        this.selected = this.images.find(image => image.color === id)?.id ?? this.visibleImages[0]?.id ?? null;
        this.failed = false;
    },
    selectImage(id) {
        this.selected = id;
        this.failed = false;
    },
});
