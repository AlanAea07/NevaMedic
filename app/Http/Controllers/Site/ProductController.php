<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sector;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'sector' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(array_keys(config('neva.product_types')))],
        ]);
        $products = Product::published()->with(['images', 'sectors', 'variants'])
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('product_type', $type))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->when($filters['sector'] ?? null, fn ($q, $slug) => $q->whereHas('sectors', fn ($q) => $q->where('slug', $slug)->where('is_active', true)))
            ->orderBy('name')->paginate(12)->withQueryString();

        return view('site.products.index', ['products' => $products, 'sectors' => Sector::where('is_active', true)->orderBy('name')->get()]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_available, 404);
        $product->load(['sectors', 'images', 'variants']);

        return view('site.products.show', compact('product'));
    }
}
