<section class="space-y-4 border-t border-neva-line pt-5">
    <h2 class="flex items-center gap-2 text-xl font-semibold"><x-ui.nav-icon name="photos" />Más fotografías del producto (opcional)</h2>
    <p class="text-sm text-neva-muted">Agrega frente, espalda o detalles sin cambiar el color. Aparecerán después de la portada en la vista general y en las flechas del catálogo. Las fotos de un color específico se agregan más abajo, dentro de ese color.</p>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        @foreach($product->images->whereNull('product_variant_id')->where('is_primary', false) as $image)
        <label class="rounded border border-neva-line p-2"><img src="{{ $image->url }}" alt="{{ $image->alt }}" class="h-28 w-full object-cover" referrerpolicy="no-referrer"><span class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_images[]" value="{{ $image->id }}" @checked(in_array($image->id, old('remove_images', [])))>Quitar al guardar</span></label>
        @endforeach
    </div>
    <label class="block">Añadir archivos JPG, PNG o WebP<input type="file" multiple name="gallery_files[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-input" @change="selectGalleryFiles($event)"></label>
    <p class="text-sm text-neva-muted">Hasta 8 archivos por carga, máximo 10 MB cada uno.</p>
    <p x-show="galleryError" x-text="galleryError" class="text-sm text-red-700" role="alert"></p>
    <div class="grid grid-cols-3 gap-2"><template x-for="url in galleryPreviews" :key="url"><img :src="url" class="h-28 w-full rounded object-cover" alt="Nueva fotografía de la galería"></template></div>
    <template x-for="(link, index) in galleryLinks" :key="index"><label class="block">Enlace de fotografía adicional<input type="url" :name="`gallery_urls[${index}]`" x-model="galleryLinks[index]" class="form-input" placeholder="https://ejemplo.com/prenda.webp"><span class="text-xs text-neva-muted">Deja vacío para omitir este enlace.</span></label></template>
    <button type="button" class="btn-secondary" @click="galleryLinks.push('')" :disabled="galleryLinks.length >= 8">Añadir enlace a la galería</button>
</section>
