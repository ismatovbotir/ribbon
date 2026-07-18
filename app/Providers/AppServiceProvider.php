<?php

namespace App\Providers;

use App\Models\Setting;
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
        // Makes the one sitewide Setting row available as $settings in the
        // storefront layout (tracking scripts, verification meta tags,
        // footer contact info, fallback SEO tags) without every individual
        // storefront page component having to pass it through ->layout().
        View::composer('layouts.storefront', function ($view) {
            $view->with('settings', Setting::current());
        });
    }
}
