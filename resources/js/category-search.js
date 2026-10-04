const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

export default (options, initial) => ({
    options,
    value: initial || '',
    query: options[initial] || '',
    open: false,
    active: -1,
    get matches() {
        const term = normalize(this.query.trim());
        return Object.entries(this.options).filter(([, label]) => normalize(label).includes(term));
    },
    search() {
        this.value = '';
        this.open = true;
        this.active = -1;
    },
    choose(key) {
        this.value = key;
        this.query = this.options[key] || '';
        this.open = false;
        this.active = -1;
    },
    close() {
        this.open = false;
        this.query = this.options[this.value] || '';
        this.active = -1;
    },
    move(step) {
        this.open = true;
        const count = this.matches.length;
        if (!count) return;
        this.active = this.active < 0 ? (step > 0 ? 0 : count - 1) : (this.active + step + count) % count;
        this.$nextTick(() => document.getElementById(`category-option-${this.active}`)?.scrollIntoView({ block: 'nearest' }));
    },
    confirm() {
        if (this.open && this.active >= 0 && this.matches[this.active]) this.choose(this.matches[this.active][0]);
    },
});
