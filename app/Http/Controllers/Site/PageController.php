<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function customization(): View
    {
        return view('site.customization');
    }

    public function contact(Request $request): View
    {
        $slug = $request->query('product');
        $product = is_string($slug) ? Product::published()->with('variants')->where('slug', $slug)->first() : null;
        $size = $request->query('size');
        $color = $request->query('color');
        $size = $product && is_string($size) && in_array($size, $product->available_sizes ?? [], true) ? $size : null;
        $color = $product?->has_variants && is_string($color) && $product->variants->where('is_available', true)->contains('color', $color) ? $color : null;
        $quoteMessage = 'Hola, me interesa cotizar '.($product?->name ?? 'uniformes Neva Medic');
        if ($size) $quoteMessage .= ', talla '.$size;
        if ($color) $quoteMessage .= ', color '.$color;

        return view('site.contact', compact('product', 'size', 'color', 'quoteMessage'));
    }

    public function privacy(): View
    {
        return view('site.privacy');
    }

    public function catalogs(): View
    {
        return view('site.catalogs.index', ['sectors' => Sector::where('is_active', true)->withCount(['products' => fn ($q) => $q->published()])->get()]);
    }
}
