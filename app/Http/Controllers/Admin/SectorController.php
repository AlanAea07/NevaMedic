<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSectorRequest;
use App\Models\ActivityLog;
use App\Models\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
        DB::transaction(function () use ($request) {
            $sector = Sector::create($request->validated());
            ActivityLog::record($request->user(), 'created', 'sector', $sector->id, null, $sector->attributesToArray());
        });

        return to_route('admin.sectors.index')->with('status', 'Sector creado.');
    }

    public function update(SaveSectorRequest $request, Sector $sector): RedirectResponse
    {
        DB::transaction(function () use ($request, $sector) {
            $sector = Sector::whereKey($sector->id)->lockForUpdate()->firstOrFail();
            $before = $sector->attributesToArray();
            $sector->update($request->validated());
            ActivityLog::record($request->user(), 'updated', 'sector', $sector->id, $before, $sector->attributesToArray());
        });

        return to_route('admin.sectors.index')->with('status', 'Sector actualizado.');
    }
}
