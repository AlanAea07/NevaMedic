import './bootstrap';

import Alpine from 'alpinejs';
import productEditor from './product-editor';
import categorySearch from './category-search';

window.Alpine = Alpine;
Alpine.data('productEditor', productEditor);
Alpine.data('categorySearch', categorySearch);

Alpine.start();
