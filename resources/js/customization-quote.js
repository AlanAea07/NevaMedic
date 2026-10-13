export default (models, initial) => ({
    models, ...initial,
    get model() { return this.models.find(model => model.slug === this.product); },
    get total() { return this.unknown ? Number(this.quantity || 0) : Object.values(this.sizes).reduce((total, value) => total + Number(value || 0), 0); },
    get sizeSummary() { return this.unknown ? 'Aún no sé las tallas; necesito asesoría' : Object.entries(this.sizes).filter(([, quantity]) => Number(quantity) > 0).map(([size, quantity]) => `${size}: ${quantity}`).join(' · ') || 'Indica las prendas de cada talla'; },
    changeModel() { this.color = ''; this.sizes = {}; this.unknown = true; },
});
