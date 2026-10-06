@php
    $selectedSizes = old('sizes_present') ? old('available_sizes', []) : ($product->available_sizes ?? []);
    if (!is_array($selectedSizes)) $selectedSizes = [];
@endphp
<fieldset class="space-y-4 rounded-lg border border-neva-line p-5" x-data="{ selectedSizes: {{ Illuminate\Support\Js::from($selectedSizes) }}, allSizes: {{ Illuminate\Support\Js::from(config('neva.sizes')) }} }">
    <legend class="px-2 text-xl font-semibold">Tallas disponibles</legend>
    <input type="hidden" name="sizes_present" value="1">
    <p class="text-sm text-neva-muted">Marca las tallas que tienes en existencia. Desmarca una talla cuando se agote y guarda el producto.</p>
    <div class="flex flex-wrap gap-3">
        @foreach(config('neva.sizes') as $size)
        <label class="cursor-pointer">
            <input type="checkbox" name="available_sizes[]" value="{{ $size }}" x-model="selectedSizes" @checked(in_array($size, $selectedSizes)) class="peer sr-only">
            <span class="inline-flex min-h-11 min-w-14 items-center justify-center rounded-lg border border-neva-line bg-white px-4 py-3 font-semibold peer-checked:border-neva-blue peer-checked:bg-neva-blue peer-checked:text-white peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-neva-blue">{{ $size }}</span>
        </label>
        @endforeach
    </div>
    <div class="flex flex-wrap gap-4 text-sm"><button type="button" class="font-semibold text-neva-blue" @click="selectedSizes = [...allSizes]">Marcar todas</button><button type="button" class="text-neva-muted" @click="selectedSizes = []">Desmarcar todas</button></div>
    <p class="text-sm text-neva-muted" aria-live="polite" x-text="selectedSizes.length ? selectedSizes.length + ' tallas seleccionadas' : 'Sin tallas seleccionadas. No se mostrarán tallas en la ficha.'"></p>
    @error('available_sizes')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
</fieldset>
