<?php

namespace App\Providers;

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
        // Garde-fou : un APP_DEBUG=true oublié dans le .env de production exposerait traces et chemins.
        if ($this->app->environment('production')) {
            config(['app.debug' => false]);
        }
    }
}
