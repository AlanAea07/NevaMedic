@props(['name', 'options', 'value' => null])
<div class="relative" x-data="categorySearch({{ Illuminate\Support\Js::from($options) }}, {{ Illuminate\Support\Js::from($value) }})" @click.outside="close()" @focusout="if (!$el.contains($event.relatedTarget)) close()">
    <label for="category-search" class="block">Categoría (tipo de producto)</label>
    <input type="hidden" name="{{ $name }}" :value="value" value="{{ $value }}">
    <div class="flex items-center gap-2">
        <input id="category-search" type="search" class="form-input" placeholder="Buscar categoría, por ejemplo: batas" autocomplete="off" x-model="query" @input="search()" @focus="open = true" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="confirm()" @keydown.escape.prevent.stop="close()" role="combobox" aria-autocomplete="list" aria-controls="category-results" :aria-expanded="open" :aria-activedescendant="active >= 0 ? `category-option-${active}` : null" aria-describedby="category-help">
        <button type="button" class="text-sm text-neva-blue" @click="choose('')" x-show="query || value">Limpiar</button>
    </div>
    <div x-cloak x-show="open" class="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded border border-neva-line bg-white shadow-lg">
        <ul id="category-results" role="listbox" aria-label="Categorías">
            <template x-for="([key, label], index) in matches" :key="key">
                <li role="option" :id="`category-option-${index}`" :aria-selected="value === key" @mousedown.prevent @click="choose(key)" class="cursor-pointer px-4 py-3 hover:bg-neva-mist" :class="{ 'bg-neva-mist text-neva-blue': active === index || value === key }" x-text="label"></li>
            </template>
        </ul>
        <p x-show="matches.length === 0" class="p-4 text-sm text-neva-muted">No hay categorías con ese nombre.</p>
    </div>
    <p id="category-help" class="mt-2 text-sm text-neva-muted" x-text="value ? 'Categoría seleccionada: ' + options[value] : 'Escribe y selecciona una categoría de los resultados. Si no eliges ninguna, se guardará sin clasificar.'"></p>
    @error($name)<p class="text-sm text-red-700">{{ $message }}</p>@enderror
</div>
