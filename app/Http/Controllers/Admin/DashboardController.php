<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductDailyMetric;
use App\Models\Sector;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'productCount' => Product::count(),
            'publishedCount' => Product::published()->count(),
            'sectorCount' => Sector::where('is_active', true)->count(),
            'views' => ProductDailyMetric::where('date', '>=', today()->subDays(29))->sum('views'),
            'products' => Product::with(['sectors', 'images'])->latest()->limit(5)->get(),
        ]);
    }
}
