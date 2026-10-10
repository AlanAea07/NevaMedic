<section class="space-y-4 border-t border-neva-line pt-5">
    <h2 class="text-xl font-semibold">Variantes por color</h2>
    <input type="hidden" name="has_variants" value="0">
    <label class="flex items-center gap-3"><input type="checkbox" name="has_variants" value="1" x-model="hasVariants">Este producto tiene variantes de color</label>
    <p class="text-sm text-neva-muted">Cada color tiene una foto principal y puede tener varias vistas adicionales. Al elegirlo, el visitante verá solo sus fotos. Al desactivar esta opción, los colores se conservan pero no se muestran al visitante.</p>
    <fieldset :disabled="!hasVariants" x-show="hasVariants" x-cloak class="space-y-5">
        <template x-for="(row, index) in variants" :key="row._key">
            <div class="space-y-3 rounded border border-neva-line p-4">
                <div class="flex items-center justify-between"><h3 class="font-semibold" x-text="row.color || 'Nuevo color'"></h3><button type="button" class="text-sm text-red-700" @click="removeVariant(index)">Quitar color</button></div>
                <input type="hidden" :name="`variants[${index}][id]`" :value="row.id">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label>Nombre del color<input class="form-input" :name="`variants[${index}][color]`" x-model="row.color" placeholder="Blanco, café, azul marino…" maxlength="80" :required="hasVariants"></label>
                    <label>Muestra de color<input type="color" class="form-input h-11" :name="`variants[${index}][color_hex]`" x-model="row.color_hex"></label>
                </div>
                <label class="block">Foto principal de este color<select class="form-input" :name="`variants[${index}][image_source]`" x-model="row.image_source" @change="failed = false"><option value="file">Subir JPG, PNG o WebP</option><option value="url">Usar enlace externo</option><option value="keep" :disabled="!row.existing">Conservar fotografía actual</option></select></label>
                <label class="block" x-show="row.image_source === 'file'">Archivo JPG, PNG o WebP<input type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" :name="`variants[${index}][image_file]`" class="form-input" :disabled="row.image_source !== 'file'" :required="hasVariants && row.image_source === 'file'" @change="selectVariantFile(row, $event)"></label>
                <label class="block" x-show="row.image_source === 'url'">Enlace público<input type="url" class="form-input" :name="`variants[${index}][image_url]`" x-model="row.image_url" maxlength="2048" :disabled="row.image_source !== 'url'" :required="hasVariants && row.image_source === 'url'" placeholder="https://ejemplo.com/filipina-blanca.webp"></label>
                <p class="text-sm text-red-700" x-show="row.error" x-text="row.error"></p>
                <template x-if="variantImage(row)"><img :src="variantImage(row)" :alt="row.color" class="h-32 w-32 rounded object-cover" referrerpolicy="no-referrer"></template>
                <div class="space-y-3 border-t border-neva-line pt-4">
                    <h4 class="flex items-center gap-2 font-semibold"><x-ui.nav-icon name="photos" />Más vistas de este color</h4>
                    <p class="text-sm text-neva-muted">Se mostrarán después de la foto principal de este color. No necesitas crear otra variante.</p>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <template x-for="photo in row.existingGallery" :key="photo.id">
                            <label class="rounded border border-neva-line p-2"><img :src="photo.url" :alt="'Vista adicional · ' + row.color" class="h-28 w-full object-cover" loading="lazy" referrerpolicy="no-referrer"><span class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" :name="`variants[${index}][remove_images][]`" :value="String(photo.id)" x-model="row.removeImages">Quitar al guardar</span></label>
                        </template>
                    </div>
                    <label class="block">Añadir fotos de este color<input type="file" multiple :name="`variants[${index}][gallery_files][]`" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-input" @change="selectVariantGalleryFiles(row, $event)"></label>
                    <p class="text-xs text-neva-muted">Hasta 8 archivos por carga, máximo 10 MB cada uno. Si hay errores al guardar, vuelve a seleccionar los archivos.</p>
                    <p x-show="row.galleryError" x-text="row.galleryError" class="text-sm text-red-700" role="alert"></p>
                    <div class="grid grid-cols-3 gap-2"><template x-for="url in row.galleryPreviews" :key="url"><img :src="url" :alt="'Nueva vista · ' + row.color" class="h-28 w-full rounded object-cover"></template></div>
                    <template x-for="(link, photoIndex) in row.galleryLinks" :key="photoIndex"><label class="block">Enlace de vista adicional<input type="url" :name="`variants[${index}][gallery_urls][${photoIndex}]`" x-model="row.galleryLinks[photoIndex]" maxlength="2048" class="form-input" placeholder="https://ejemplo.com/espalda.webp"><span class="text-xs text-neva-muted">Deja vacío para omitir este enlace.</span></label></template>
                    <button type="button" class="btn-secondary" @click="row.galleryLinks.push('')" :disabled="row.galleryLinks.length >= 8">Añadir enlace para este color</button>
                </div>
            </div>
        </template>
        <button type="button" class="btn-secondary" @click="addVariant()" :disabled="variants.length >= 20">Añadir color</button>
        <p class="text-sm text-neva-muted">Hasta 20 colores. Los colores que quites se eliminarán al guardar. Puedes probarlos en la previsualización de la tarjeta.</p>
    </fieldset>
</section>
