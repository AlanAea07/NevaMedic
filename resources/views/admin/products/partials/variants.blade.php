<section class="space-y-4 border-t border-neva-line pt-5">
    <h2 class="text-xl font-semibold">Variantes por color</h2>
    <input type="hidden" name="has_variants" value="0">
    <label class="flex items-center gap-3"><input type="checkbox" name="has_variants" value="1" x-model="hasVariants">Este producto tiene variantes de color</label>
    <p class="text-sm text-neva-muted">Cada color debe tener su propia fotografía. Al desactivar esta opción, los colores se conservan pero no se muestran al visitante.</p>
    <fieldset :disabled="!hasVariants" x-show="hasVariants" x-cloak class="space-y-5">
        <template x-for="(row, index) in variants" :key="row._key">
            <div class="space-y-3 rounded border border-neva-line p-4">
                <div class="flex items-center justify-between"><h3 class="font-semibold" x-text="row.color || 'Nuevo color'"></h3><button type="button" class="text-sm text-red-700" @click="removeVariant(index)">Quitar color</button></div>
                <input type="hidden" :name="`variants[${index}][id]`" :value="row.id">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label>Nombre del color<input class="form-input" :name="`variants[${index}][color]`" x-model="row.color" placeholder="Blanco, café, azul marino…" maxlength="80" :required="hasVariants"></label>
                    <label>Muestra de color<input type="color" class="form-input h-11" :name="`variants[${index}][color_hex]`" x-model="row.color_hex"></label>
                </div>
                <label class="block">Fotografía de este color<select class="form-input" :name="`variants[${index}][image_source]`" x-model="row.image_source" @change="failed = false"><option value="file">Subir WebP</option><option value="url">Usar enlace externo</option><option value="keep" :disabled="!row.existing">Conservar fotografía actual</option></select></label>
                <label class="block" x-show="row.image_source === 'file'">Archivo JPG, PNG o WebP<input type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" :name="`variants[${index}][image_file]`" class="form-input" :disabled="row.image_source !== 'file'" :required="hasVariants && row.image_source === 'file'" @change="selectVariantFile(row, $event)"></label>
                <label class="block" x-show="row.image_source === 'url'">Enlace público<input type="url" class="form-input" :name="`variants[${index}][image_url]`" x-model="row.image_url" maxlength="2048" :disabled="row.image_source !== 'url'" :required="hasVariants && row.image_source === 'url'" placeholder="https://ejemplo.com/filipina-blanca.webp"></label>
                <p class="text-sm text-red-700" x-show="row.error" x-text="row.error"></p>
                <template x-if="variantImage(row)"><img :src="variantImage(row)" :alt="row.color" class="h-32 w-32 rounded object-cover" referrerpolicy="no-referrer"></template>
            </div>
        </template>
        <button type="button" class="btn-secondary" @click="addVariant()" :disabled="variants.length >= 20">Añadir color</button>
        <p class="text-sm text-neva-muted">Hasta 20 colores. Los colores que quites se eliminarán al guardar. Puedes probarlos en la previsualización de la tarjeta.</p>
    </fieldset>
</section>
