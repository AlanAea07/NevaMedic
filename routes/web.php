<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\CatalogController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\ProductController;
use App\Http\Middleware\EnsureAdministrator;
use App\Http\Middleware\EnsureCatalogAccess;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/productos', [ProductController::class, 'index'])->name('products.index');
Route::get('/productos/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/personalizacion', [PageController::class, 'customization'])->name('customization');
Route::get('/catalogos', [PageController::class, 'catalogs'])->name('catalogs.index');
Route::get('/catalogos/{sector:slug}/descargar', [CatalogController::class, 'download'])->middleware('throttle:30,1')->name('catalogs.download');
Route::get('/catalogos/{sector:slug}/vista-previa', [CatalogController::class, 'preview'])->middleware('throttle:30,1')->name('catalogs.preview');
Route::get('/contacto', [PageController::class, 'contact'])->name('contact');
Route::get('/aviso-de-privacidad', [PageController::class, 'privacy'])->name('privacy');

Route::get('/dashboard', function () {
    return to_route(auth()->user()->isAdministrator() ? 'admin.dashboard' : 'admin.products.index');
})->middleware(['auth', EnsureCatalogAccess::class])->name('dashboard');

Route::middleware(['auth', EnsureAdministrator::class])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
