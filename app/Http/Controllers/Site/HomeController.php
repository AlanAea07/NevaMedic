<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sector;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('site.home', [
            'sectors' => Sector::where('is_active', true)->orderBy('id')->get(),
            'products' => Product::published()->where('is_featured', true)->with(['images', 'sectors', 'variants'])->latest()->limit(3)->get(),
        ]);
    }
}
