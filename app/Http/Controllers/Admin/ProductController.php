<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductRequest;
use App\Models\Product;
use App\Models\Sector;
use App\Services\Catalog\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $products = Product::with(['sectors', 'images'])->when($filters['q'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))->latest()->paginate(15)->withQueryString();

        return view('admin.products.index', compact('products'));
    }

    public function trash(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $products = Product::onlyTrashed()->with(['images', 'variants'])
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('name', 'like', '%'.$term.'%'))
            ->orderByDesc('deleted_at')->paginate(15)->withQueryString();

        return view('admin.products.trash', compact('products'));
    }

    public function restore(Request $request, int $id, ProductService $service): RedirectResponse
    {
        $service->restore($id, $request->user());

        return to_route('admin.products.trash')->with('status', 'Producto restaurado con sus imágenes, colores y disponibilidad anteriores.');
    }

    public function create(): View
    {
        return view('admin.products.create', ['product' => new Product(['is_available' => true]), 'sectors' => Sector::orderBy('name')->get()]);
    }

    public function store(SaveProductRequest $request, ProductService $service): RedirectResponse
    {
        $product = $service->save(new Product, $request->validated(), $request->user());

        return to_route('admin.products.edit', $product)->with('status', 'Producto creado.');
    }

    public function edit(Product $product): View
    {
        $product->load(['sectors', 'variants', 'images']);

        return view('admin.products.edit', ['product' => $product, 'sectors' => Sector::orderBy('name')->get()]);
    }

    public function update(SaveProductRequest $request, Product $product, ProductService $service): RedirectResponse
    {
        $service->save($product, $request->validated(), $request->user());

        return back()->with('status', 'Producto actualizado.');
    }

    public function destroy(Request $request, Product $product, ProductService $service): RedirectResponse
    {
        $service->delete($product, $request->user());

        return to_route('admin.products.index')->with('status', 'Producto enviado a la papelera. Puedes recuperarlo durante 14 días.');
    }

    public function preview(Product $product): View
    {
        $product->load(['sectors', 'images', 'variants']);

        return view('site.products.show', ['product' => $product, 'preview' => true]);
    }
}
