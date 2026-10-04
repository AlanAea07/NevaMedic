<?php

namespace App\Providers;

use App\Models\Sector;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('site.partials.navigation', function ($view) {
            $view->with('navigationSectors', Sector::where('is_active', true)->orderBy('id')->get(['name', 'slug']));
        });
    }
}
