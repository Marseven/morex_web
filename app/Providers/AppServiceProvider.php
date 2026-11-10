<?php

namespace App\Providers;

use App\Contracts\PushNotifier;
use App\Services\WebPushNotifier;
use Illuminate\Support\ServiceProvider;
use Minishlink\WebPush\WebPush;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PushNotifier::class, WebPushNotifier::class);
        $this->app->bind(WebPush::class, fn () => WebPushNotifier::makeClient());
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
