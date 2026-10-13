<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuoteController extends Controller
{
    public function create(Request $request)
    {
        $products = Product::published()->with(['images', 'variants'])->orderBy('name')->get();
        $models = $products->map(fn ($product) => [
            'slug' => $product->slug, 'name' => $product->name,
            'image' => $product->cover_image?->url,
            'sizes' => $product->available_sizes ?? [],
            'colors' => $product->has_variants ? $product->variants->where('is_available', true)->pluck('color')->values()->all() : [],
        ])->values();
        $selected = $models->firstWhere('slug', $request->old('product', $request->query('product')));
        $size = $request->query('size');
        $color = $request->old('color', $request->query('color'));
        $initial = [
            'product' => $selected['slug'] ?? '',
            'color' => in_array($color, $selected['colors'] ?? [], true) ? $color : '',
            'sizes' => $request->old('sizes', is_string($size) && in_array($size, $selected['sizes'] ?? [], true) ? [$size => 1] : []),
            'unknown' => (bool) $request->old('unknown_sizes', ! is_string($size) || ! in_array($size, $selected['sizes'] ?? [], true)),
            'quantity' => $request->old('quantity', ''),
            'logo' => $request->old('logo', 'no'),
            'institution' => $request->old('institution', ''),
            'notes' => $request->old('notes', ''),
        ];
        if (empty($selected['sizes'])) {
            $initial['unknown'] = true;
        }

        return view('site.customization', compact('models', 'initial'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product' => ['nullable', 'string', 'max:180'],
            'color' => ['nullable', 'string', 'max:80'],
            'unknown_sizes' => ['required', 'boolean'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'sizes' => ['nullable', 'array', 'max:20'],
            'sizes.*' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'logo' => ['required', Rule::in(['no', 'yes', 'advice'])],
            'institution' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:700'],
        ]);
        $product = filled($data['product'] ?? null) ? Product::published()->with('variants')->where('slug', $data['product'])->first() : null;
        if (filled($data['product'] ?? null) && ! $product) {
            throw ValidationException::withMessages(['product' => 'Este modelo ya no está disponible. Elige otro o solicita asesoría.']);
        }
        $color = $data['color'] ?? null;
        if ($color && (! $product?->has_variants || ! $product->variants->where('is_available', true)->contains('color', $color))) {
            throw ValidationException::withMessages(['color' => 'Elige un color disponible para este modelo.']);
        }
        $sizes = [];
        if (! $data['unknown_sizes']) {
            foreach ($data['sizes'] ?? [] as $size => $quantity) {
                if (! $quantity) {
                    continue;
                }
                if (! $product || ! in_array($size, $product->available_sizes ?? [], true)) {
                    throw ValidationException::withMessages(['sizes' => 'Selecciona tallas disponibles para el modelo.']);
                }
                $sizes[$size] = (int) $quantity;
            }
            if (! $sizes || array_sum($sizes) > 10000) {
                throw ValidationException::withMessages(['sizes' => 'Indica al menos una prenda por talla, hasta 10,000 en total, o marca «Aún no sé las tallas».']);
            }
        }
        $lines = ['Hola, me interesa una cotización de Neva Medic.', 'Producto: '.($product?->name ?? 'Necesito asesoría para elegir el modelo')];
        $lines[] = 'Color: '.($color ?: 'Por definir');
        $total = $sizes ? array_sum($sizes) : ($data['quantity'] ?? null);
        $lines[] = 'Cantidad aproximada: '.($total ? $total.((int) $total === 1 ? ' prenda' : ' prendas') : 'Por definir');
        $lines[] = $sizes ? 'Tallas: '.collect($sizes)->map(fn ($quantity, $size) => $size.': '.$quantity)->join(', ') : 'Tallas: aún no sé las tallas; necesito asesoría.';
        $lines[] = 'Logotipo: '.match ($data['logo']) {
            'yes' => 'Sí; compartiré el archivo por WhatsApp.',
            'advice' => 'Necesito asesoría.',
            default => 'No solicitado.',
        };
        if ($data['logo'] === 'yes' && filled($data['institution'] ?? null)) {
            $lines[] = 'Institución o empresa: '.$data['institution'];
        }
        if (filled($data['notes'] ?? null)) {
            $lines[] = 'Bordado y acabados: '.$data['notes'];
        }
        if ($product) {
            $lines[] = 'Enlace del producto: '.route('products.show', $product->slug);
        }
        $lines[] = 'Quedo pendiente de confirmar detalles y precio final con su equipo.';
        $number = (string) config('neva.whatsapp');
        if (! preg_match('/^[1-9][0-9]{9,14}$/', $number)) {
            throw ValidationException::withMessages(['contact' => 'WhatsApp no está disponible en este momento. Intenta más tarde.']);
        }

        return redirect()->away('https://wa.me/'.$number.'?text='.rawurlencode(implode("\n", $lines)));
    }
}
