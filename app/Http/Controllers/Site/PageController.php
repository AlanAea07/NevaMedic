<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use Illuminate\View\View;

class PageController extends Controller
{
    public function customization(): View
    {
        return view('site.customization');
    }

    public function contact(): View
    {
        return view('site.contact');
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
