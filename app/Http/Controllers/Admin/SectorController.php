<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSectorRequest;
use App\Models\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SectorController extends Controller
{
    public function index(): View
    {
        return view('admin.sectors.index', ['sectors' => Sector::withCount('products')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.sectors.form', ['sector' => new Sector(['is_active' => true])]);
    }

    public function edit(Sector $sector): View
    {
        return view('admin.sectors.form', compact('sector'));
    }

    public function store(SaveSectorRequest $request): RedirectResponse
    {
        Sector::create($request->validated());

        return to_route('admin.sectors.index')->with('status', 'Sector creado.');
    }

    public function update(SaveSectorRequest $request, Sector $sector): RedirectResponse
    {
        $sector->update($request->validated());

        return to_route('admin.sectors.index')->with('status', 'Sector actualizado.');
    }
}
