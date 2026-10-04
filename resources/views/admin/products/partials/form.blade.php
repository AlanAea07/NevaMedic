@php
    $currentImage = $product->images->first();
    $imageState = ['source' => old('image_source', 'keep'), 'url' => old('image_url', $currentImage?->external_url), 'existing' => $currentImage?->url];
    $colorRows = old('variants', $product->variants->map(fn ($variant) => ['id' => $variant->id, 'color' => $variant->color, 'color_hex' => $variant->color_hex ?: '#1765d8', 'image_source' => 'keep'])->all());
    $imageState['variants'] = collect(is_array($colorRows) ? $colorRows : [])->filter(fn ($row) => is_array($row))->map(function ($row) use ($product) {
        $image = !empty($row['id']) ? $product->images->firstWhere('product_variant_id', $row['id']) : null;
        return ['id' => $row['id'] ?? '', 'color' => $row['color'] ?? '', 'color_hex' => $row['color_hex'] ?? '#1765d8', 'image_source' => $row['image_source'] ?? 'file', 'image_url' => $row['image_url'] ?? $image?->external_url ?? '', 'existing' => $image?->url ?? ''];
    })->values()->all();
    $imageState['hasVariants'] = (bool) old('has_variants', $product->has_variants);
    $imageState['galleryLinks'] = old('gallery_urls', []);
@endphp
<div class="grid gap-6 xl:grid-cols-3" x-data="productEditor({{ Illuminate\Support\Js::from($imageState) }})" @input="sync()" @change="sync()">
<div class="panel space-y-5 xl:col-span-2">
<h2 class="text-xl font-semibold">Información general</h2>
<x-ui.category-search name="product_type" :options="config('neva.product_types')" :value="old('product_type', $product->product_type)" />
@foreach(['name' => ['Nombre', 150], 'sku' => ['SKU (opcional)', 50], 'short_description' => ['Descripción corta', 280], 'material' => ['Material (opcional)', 150]] as $field => [$label, $length])
<label class="block">{{ $label }}<input class="form-input" name="{{ $field }}" value="{{ old($field, $product->$field) }}" maxlength="{{ $length }}" @required(in_array($field, ['name', 'slug', 'short_description']))></label>
@endforeach
<label class="block" for="product-slug">Nombre para el enlace del producto
    <input id="product-slug" class="form-input" name="slug" value="{{ old('slug', $product->slug) }}" maxlength="180" placeholder="filipina-azul" pattern="[a-z0-9]+(-[a-z0-9]+)*" aria-describedby="product-slug-help" required>
</label>
<p id="product-slug-help" class="text-sm text-neva-muted">Ejemplo: <strong>filipina-azul</strong>. Escribe un nombre corto, sin espacios ni acentos y separado con guiones. No pongas https:// ni .com.</p>
@error('slug')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
<label class="block">Descripción<textarea class="form-input" name="description" rows="5" maxlength="10000">{{ old('description', $product->description) }}</textarea></label>
<label class="block">Cuidados<textarea class="form-input" name="care_instructions" rows="3" maxlength="2000">{{ old('care_instructions', $product->care_instructions) }}</textarea></label>
<fieldset class="space-y-4 border-t border-neva-line pt-5">
    <legend class="text-xl font-semibold">Imagen principal</legend>
    <label class="block">Origen de la imagen
        <select class="form-input" name="image_source" x-model="source">
            <option value="keep" @selected(old('image_source', 'keep') === 'keep')>{{ $currentImage ? 'Conservar imagen actual' : 'Sin imagen por ahora' }}</option>
            <option value="file" @selected(old('image_source') === 'file')>Subir archivo WebP</option>
            <option value="url" @selected(old('image_source') === 'url')>Usar enlace externo</option>
        </select>
    </label>
    <div x-cloak x-show="source === 'file'" class="space-y-2">
        <label class="block">Archivo WebP<input type="file" name="image_file" accept=".webp,image/webp" class="form-input" :disabled="source !== 'file'" :required="source === 'file'" @change="selectFile($event)"></label>
        <p class="text-sm text-neva-muted">Solo WebP, máximo 2 MB. Si el formulario muestra errores, vuelve a seleccionar el archivo.</p>
    </div>
    <div x-cloak x-show="source === 'url'" class="space-y-2">
        <label class="block">Enlace público de la imagen<input type="url" name="image_url" class="form-input" maxlength="2048" placeholder="https://ejemplo.com/imagen.webp" x-model="url" @input.debounce.400ms="refreshImage()" :disabled="source !== 'url'" :required="source === 'url'"></label>
        <p class="text-sm text-neva-muted">Pega un enlace directo a la imagen o un enlace de Google Drive compartido con «Cualquier persona con el enlace». Un enlace de búsqueda de Google o de una página web no es una imagen.</p>
    </div>
    <p x-cloak x-show="error" x-text="error" class="text-sm text-red-700" role="alert"></p>
    @error('image_file')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
    @error('image_url')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
</fieldset>
@include('admin.products.partials.gallery')
@include('admin.products.partials.variants')
</div>
<div class="panel space-y-6 self-start">
<label class="block">Precio de referencia (MXN)<input type="number" step="0.01" min="0" max="99999999.99" class="form-input" name="reference_price" value="{{ old('reference_price', $product->reference_price) }}" required></label>
<fieldset><legend class="font-semibold">Sectores</legend><div class="mt-3 space-y-3">
@forelse($sectors as $sector)
<label class="flex items-center gap-3"><input type="checkbox" name="sectors[]" value="{{ $sector->id }}" data-label="{{ $sector->name }}" data-active="{{ $sector->is_active ? '1' : '0' }}" @checked(in_array($sector->id, old('sectors', $product->sectors->modelKeys())))>{{ $sector->name }} {{ $sector->is_active ? '' : '(inactivo)' }}</label>
@empty<p class="text-neva-muted">Primero crea un sector.</p><a href="{{ route('admin.sectors.create') }}" class="text-neva-blue">Crear sector</a>@endforelse
</div></fieldset>
@foreach(['is_available' => 'Disponible · mostrar en el catálogo', 'is_featured' => 'Destacado en inicio'] as $field => $label)
<input type="hidden" name="{{ $field }}" value="0"><label class="flex items-center gap-3"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $product->$field))>{{ $label }}</label>
@endforeach
<p class="text-sm text-neva-muted">Al desactivar «Disponible», el producto queda oculto para los visitantes.</p>
<button class="btn-primary w-full">Guardar producto</button><a href="{{ route('admin.products.index') }}" class="btn-secondary w-full">Cancelar</a>
<section class="border-t border-neva-line pt-6" aria-label="Previsualización de la tarjeta">
    <h2 class="mb-4 font-semibold">Así se verá en el catálogo</h2>
    <p x-cloak x-show="!available" class="mb-4 rounded bg-amber-50 p-3 text-sm text-amber-900">Producto oculto. Activa «Disponible» para mostrarlo en el catálogo.</p>
    <article class="overflow-hidden rounded border border-neva-line bg-white">
        <template x-if="cardImage"><img :src="cardImage" alt="Previsualización de la imagen principal" class="h-72 w-full object-cover" x-show="!failed" x-on:error="failed = true" @load="failed = false" referrerpolicy="no-referrer"></template>
        <div x-show="!cardImage || failed" class="flex h-72 items-center justify-center bg-neva-mist p-6 text-center text-neva-muted" x-text="failed ? 'No se pudo cargar la imagen. Revisa el enlace y sus permisos.' : 'Fotografía pendiente'"></div>
        <div x-cloak x-show="hasVariants" class="flex flex-wrap gap-2 p-3"><button type="button" class="rounded border px-2 py-1 text-sm" @click="previewColor = ''; failed = false">Principal</button><template x-for="row in variants" :key="row._key"><button type="button" class="rounded border px-2 py-1 text-sm" :class="{ 'border-neva-blue': previewColor === row._key }" @click="previewColor = row._key; failed = false" x-text="row.color || 'Nuevo color'"></button></template></div>
        <div class="space-y-3 p-6">
            <p class="text-xs font-semibold uppercase tracking-widest text-neva-blue" x-text="sectors"></p>
            <h3 class="font-display text-3xl" x-text="name || 'Nombre del producto'"></h3>
            <p class="text-sm text-neva-muted" x-text="description || 'Descripción corta del producto'"></p>
            <p class="font-semibold" x-text="formattedPrice"></p>
            <p class="text-sm" x-text="(available ? 'Disponible' : 'No disponible') + ' · Precio de referencia'"></p>
            <span class="inline-block text-sm font-semibold text-neva-blue">Ver producto →</span>
        </div>
    </article>
</section>
</div>
</div>
