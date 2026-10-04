<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\ProductController;
use App\Http\Middleware\EnsureAdministrator;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/productos', [ProductController::class, 'index'])->name('products.index');
Route::get('/productos/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/personalizacion', [PageController::class, 'customization'])->name('customization');
Route::get('/catalogos', [PageController::class, 'catalogs'])->name('catalogs.index');
Route::get('/contacto', [PageController::class, 'contact'])->name('contact');
Route::get('/aviso-de-privacidad', [PageController::class, 'privacy'])->name('privacy');

Route::get('/dashboard', function () {
    return to_route('admin.dashboard');
})->middleware(['auth', EnsureAdministrator::class])->name('dashboard');

Route::middleware(['auth', EnsureAdministrator::class])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
