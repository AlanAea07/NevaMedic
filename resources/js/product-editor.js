export function externalImageUrl(value) {
    try {
        const url = new URL(value);
        if (!['http:', 'https:'].includes(url.protocol)) return '';
        if (url.hostname === 'drive.google.com') {
            const id = url.pathname.match(/\/file\/d\/([a-zA-Z0-9_-]+)/)?.[1] || url.searchParams.get('id');
            if (id && /^[a-zA-Z0-9_-]+$/.test(id)) {
                return `https://drive.google.com/thumbnail?id=${id}&sz=w1200`;
            }
        }
        return url.href;
    } catch {
        return '';
    }
}

export default (initial) => ({
    source: initial.source,
    url: initial.url || '',
    existing: initial.existing || '',
    fileUrl: '',
    preview: '',
    error: '',
    failed: false,
    name: '',
    description: '',
    price: '',
    available: false,
    sectors: '',
    hasVariants: !!initial.hasVariants,
    variants: (initial.variants || []).map((row, index) => ({ galleryLinks: [], existingGallery: [], removeImages: [], ...row, _key: index + 1, fileUrl: '', error: '', galleryPreviews: [], galleryError: '' })),
    nextKey: (initial.variants || []).length + 1,
    previewColor: '',
    galleryLinks: initial.galleryLinks || [],
    galleryPreviews: [],
    galleryError: '',
    get cardImage() {
        const row = this.hasVariants && this.variants.find(row => row._key === this.previewColor);
        return row ? this.variantImage(row) : this.preview;
    },
    variantImage(row) {
        return row.image_source === 'file' ? row.fileUrl : row.image_source === 'url' ? externalImageUrl(row.image_url) : row.existing;
    },
    addVariant() {
        this.variants.push({ _key: this.nextKey++, id: '', color: '', color_hex: '#1765d8', image_source: 'file', image_url: '', existing: '', fileUrl: '', error: '', galleryLinks: [], existingGallery: [], removeImages: [], galleryPreviews: [], galleryError: '' });
    },
    removeVariant(index) {
        const row = this.variants[index];
        if (row.fileUrl) URL.revokeObjectURL(row.fileUrl);
        row.galleryPreviews.forEach(url => URL.revokeObjectURL(url));
        if (this.previewColor === row._key) this.previewColor = '';
        this.variants.splice(index, 1);
    },
    fileError(file) {
        if (!/\.(jpe?g|png|webp)$/i.test(file.name) || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return 'Solo se permiten archivos JPG, PNG o WebP.';
        return file.size > 10 * 1024 * 1024 ? 'La imagen no debe superar 10 MB.' : '';
    },
    selectVariantFile(row, event) {
        if (row.fileUrl) URL.revokeObjectURL(row.fileUrl);
        row.fileUrl = ''; row.error = ''; this.failed = false;
        const file = event.target.files[0];
        if (!file) return;
        row.error = this.fileError(file);
        if (row.error) event.target.value = '';
        else row.fileUrl = URL.createObjectURL(file);
    },
    selectGalleryFiles(event) {
        this.galleryPreviews.forEach(url => URL.revokeObjectURL(url));
        this.galleryPreviews = [];
        const files = [...event.target.files];
        this.galleryError = files.length > 8 ? 'Selecciona como máximo 8 imágenes.' : files.map(file => this.fileError(file)).find(Boolean) || '';
        if (this.galleryError) event.target.value = '';
        else this.galleryPreviews = files.map(file => URL.createObjectURL(file));
    },
    selectVariantGalleryFiles(row, event) {
        row.galleryPreviews.forEach(url => URL.revokeObjectURL(url));
        row.galleryPreviews = [];
        const files = [...event.target.files];
        row.galleryError = files.length > 8 ? 'Selecciona como máximo 8 imágenes.' : files.map(file => this.fileError(file)).find(Boolean) || '';
        if (row.galleryError) event.target.value = '';
        else row.galleryPreviews = files.map(file => URL.createObjectURL(file));
    },
    init() {
        this.$nextTick(() => this.sync());
        this.$watch('source', () => this.refreshImage());
        this.refreshImage();
    },
    sync() {
        const form = this.$el.closest('form');
        this.name = form.elements.namedItem('name').value;
        this.description = form.elements.namedItem('short_description').value;
        this.price = form.elements.namedItem('reference_price').value;
        this.available = form.querySelector('input[type=checkbox][name=is_available]').checked;
        this.sectors = [...form.querySelectorAll('input[name="sectors[]"]:checked')]
            .filter(input => input.dataset.active === '1')
            .map(input => input.dataset.label).join(' · ');
    },
    get formattedPrice() {
        return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(this.price) || 0) + ' MXN';
    },
    refreshImage() {
        this.failed = false;
        this.preview = this.source === 'file' ? this.fileUrl : this.source === 'url' ? externalImageUrl(this.url) : this.existing;
    },
    selectFile(event) {
        if (this.fileUrl) URL.revokeObjectURL(this.fileUrl);
        this.fileUrl = '';
        this.error = '';
        const file = event.target.files[0];
        if (file) {
            if (!/\.(jpe?g|png|webp)$/i.test(file.name) || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                this.error = 'Solo se permiten archivos JPG, PNG o WebP.';
            } else if (file.size > 10 * 1024 * 1024) {
                this.error = 'La imagen no debe superar 10 MB.';
            } else {
                this.fileUrl = URL.createObjectURL(file);
            }
            if (this.error) event.target.value = '';
        }
        this.refreshImage();
    },
    destroy() {
        if (this.fileUrl) URL.revokeObjectURL(this.fileUrl);
        this.galleryPreviews.forEach(url => URL.revokeObjectURL(url));
        this.variants.forEach(row => {
            if (row.fileUrl) URL.revokeObjectURL(row.fileUrl);
            row.galleryPreviews.forEach(url => URL.revokeObjectURL(url));
        });
    },
});
