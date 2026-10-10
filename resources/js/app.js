import './bootstrap';
import './home-motion';

import Alpine from 'alpinejs';
import productEditor from './product-editor';
import categorySearch from './category-search';
import productGallery from './product-gallery';
import catalogCard from './catalog-card';

window.Alpine = Alpine;
Alpine.data('productEditor', productEditor);
Alpine.data('categorySearch', categorySearch);
Alpine.data('productGallery', productGallery);
Alpine.data('catalogCard', catalogCard);

Alpine.start();
