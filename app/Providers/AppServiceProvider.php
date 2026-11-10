<?php

namespace App\Providers;

use App\Contracts\PushNotifier;
use App\Models\BudgetCycle;
use App\Models\Transaction;
use App\Services\BudgetAlertService;
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
        $this->app->scoped(BudgetAlertService::class);
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

        // Plafonds (module plafonds-alertes) : toute dépense confirmée recalcule le plafond de sa catégorie.
        // La synchro désactive ces événements et vérifie tout l'utilisateur après validation.
        // Un cycle créé, clôturé ou supprimé change la période des plafonds
        BudgetCycle::saved(fn () => app(BudgetAlertService::class)->forgetPeriods());
        BudgetCycle::deleted(fn () => app(BudgetAlertService::class)->forgetPeriods());

        Transaction::saved(function (Transaction $transaction) {
            $relevant = $transaction->wasRecentlyCreated
                || $transaction->wasChanged(['amount', 'category_id', 'status', 'date', 'type']);

            if ($relevant
                && $transaction->type === 'expense'
                && $transaction->status !== Transaction::STATUS_PENDING
                && $transaction->category_id !== null
                && $transaction->user !== null) {
                app(BudgetAlertService::class)->checkCategory($transaction->user, $transaction->category_id);
            }
        });
    }
}
