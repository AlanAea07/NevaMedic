<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SectorController;
use App\Http\Middleware\EnsureAdministrator;
use App\Http\Middleware\EnsureCatalogAccess;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureCatalogAccess::class])->group(function () {
    Route::get('productos/papelera', [ProductController::class, 'trash'])->name('products.trash');
    Route::post('productos/papelera/{id}/restaurar', [ProductController::class, 'restore'])->whereNumber('id')->name('products.restore');
    Route::get('productos/{product}/vista-previa', [ProductController::class, 'preview'])->name('products.preview');
    Route::resource('productos', ProductController::class)->parameters(['productos' => 'product'])->names('products')->except('show');
    Route::middleware(EnsureAdministrator::class)->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('cuentas', [AccountController::class, 'index'])->name('accounts.index');
        Route::patch('cuentas/{account}', [AccountController::class, 'update'])->name('accounts.update');
        Route::resource('sectores', SectorController::class)->parameters(['sectores' => 'sector'])->names('sectors')->except(['show', 'destroy']);
        Route::get('estadisticas', [ReportController::class, 'statistics'])->name('statistics.index');
        Route::get('actividad', [ReportController::class, 'activity'])->name('activity.index');
        Route::get('actividad/{entry}', [ReportController::class, 'activityShow'])->name('activity.show');
    });
});
